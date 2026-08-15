<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions\Order;

use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Service;
use App\Modules\Clinic\Actions\Prescription\CloseTicket;
use App\Modules\Clinic\Models\Prescription;
use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\CostSource;
use App\Modules\Sales\Enums\OrderDeliveryType;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\OrderType;
use App\Modules\Sales\Enums\PaymentStatus;
use App\Modules\Sales\Models\Order;
use App\Support\Documents\DocumentNumber;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chek yoki buyurtma yaratish — PROJECT.md 7.3, 7.13, §15 #19.
 *
 * Ombor bu yerda **tegilmaydi**: tovar topshirish paytida chiqadi
 * (`DeliverOrder`). Tez savdoda (`type = quick`) topshirish shu
 * amalning o'zidan darrov chaqiriladi — chek tovar berilganda tug'iladi.
 *
 * Narx **katalogdan** olinadi, so'rovdan emas: aks holda so'rovni
 * qo'lda o'zgartirib istalgan narxda sotib yuborish mumkin bo'lardi.
 * Sotuvchining ixtiyoridagi yagona dastak — chegirma, u esa limit va
 * ruxsat bilan cheklangan.
 */
final class CreateOrder
{
    public function __construct(
        private readonly DeliverOrder $deliver,
        private readonly CloseTicket $closeTicket,
    ) {}

    /**
     * @param  array<int, array{kind: string, id: int, quantity: int, discount?: string, cost_total?: string, custom_lens_params?: array<string, mixed>|null}>  $items
     */
    public function handle(
        User $author,
        Branch $branch,
        OrderType $type,
        array $items,
        ?int $customerId = null,
        ?Money $orderDiscount = null,
        OrderDeliveryType $deliveryType = OrderDeliveryType::Pickup,
        ?string $dueDate = null,
        ?int $prescriptionId = null,
    ): Order {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => __('sales::order.no_items'),
            ]);
        }

        return DB::transaction(function () use (
            $author, $branch, $type, $items, $customerId, $orderDiscount, $deliveryType, $dueDate, $prescriptionId
        ): Order {
            $order = Order::create([
                'number' => DocumentNumber::next('order', $branch->id, $branch->code),
                'branch_id' => $branch->id,
                'customer_id' => $customerId,
                'shift_id' => $this->openShiftId($branch->id),
                'type' => $type,
                'status' => OrderStatus::New,
                'payment_status' => PaymentStatus::Unpaid,
                'prescription_id' => $prescriptionId,
                'delivery_type' => $deliveryType,
                'due_date' => $dueDate,
                'created_by' => $author->id,
            ]);

            $subtotal = Money::zero();
            $discount = $orderDiscount ?? Money::zero();

            foreach ($items as $row) {
                [$lineSubtotal, $lineDiscount] = $this->addItem($order, $branch, $row);

                $subtotal = $subtotal->plus($lineSubtotal);
                $discount = $discount->plus($lineDiscount);
            }

            $this->applyTotals($author, $order, $subtotal, $discount);
            $this->closeTicketFor($order, $branch->id);

            // Chek tovar berilganda tug'iladi (ENUMS.md §4) — shuning
            // uchun tez savdo darrov topshiriladi va daromad shu payt
            // tan olinadi.
            //
            // Topshirish **shu tranzaksiya ichida**: qoldiq yetmasa
            // chek ham qolmasligi kerak, aks holda ombordan chiqmagan
            // tovar uchun ochilgan hujjat osilib qolardi.
            return $type->deliversImmediately()
                ? $this->deliver->handle($author, $order)
                : $order;
        });
    }

    /**
     * Bitta satrni qo'shadi va uning (summa, chegirma) juftligini qaytaradi.
     *
     * @param  array{kind: string, id: int, quantity: int, discount?: string, cost_total?: string, custom_lens_params?: array<string, mixed>|null}  $row
     * @return array{0: Money, 1: Money}
     */
    private function addItem(Order $order, Branch $branch, array $row): array
    {
        $quantity = $row['quantity'];
        [$itemableType, $price, $costSource] = $this->resolveItemable($branch, $row);

        $lineSubtotal = $price->multipliedBy($quantity);
        $lineDiscount = Money::of($row['discount'] ?? '0');

        if ($lineDiscount->greaterThan($lineSubtotal)) {
            throw ValidationException::withMessages([
                'items' => __('sales::order.discount_exceeds_line'),
            ]);
        }

        $order->items()->create([
            'itemable_type' => $itemableType,
            'itemable_id' => $row['id'],
            'quantity' => $quantity,
            'price' => $price->toString(),
            'discount' => $lineDiscount->toString(),
            'total' => $lineSubtotal->minus($lineDiscount)->toString(),
            'cost_total' => Money::of($row['cost_total'] ?? '0')->toString(),
            'cost_source' => $costSource,
            'custom_lens_params' => $row['custom_lens_params'] ?? null,
        ]);

        return [$lineSubtotal, $lineDiscount];
    }

    /**
     * Sotilayotgan narsani va uning narxini aniqlaydi.
     *
     * Individual linza alohida jadval emas: u xizmat satri bo'lib
     * keladi, `custom_lens_params` bilan va tannarxi qo'lda kiritilgan
     * holda (`manual`, ANALIZ 3.9). Shunda ombordan o'tmagan linzaning
     * tannarxi ham foyda hisobotiga tushadi.
     *
     * @param  array{kind: string, id: int, quantity: int, discount?: string, cost_total?: string, custom_lens_params?: array<string, mixed>|null}  $row
     * @return array{0: class-string, 1: Money, 2: CostSource}
     */
    private function resolveItemable(Branch $branch, array $row): array
    {
        if ($row['kind'] === 'service') {
            $service = Service::query()->findOrFail($row['id']);

            $manual = ($row['cost_total'] ?? null) !== null;

            return [
                Service::class,
                $service->price,
                $manual ? CostSource::Manual : CostSource::None,
            ];
        }

        $variant = ProductVariant::query()->findOrFail($row['id']);
        $price = Price::resolveFor($variant->id, $branch->id);

        if (! $price instanceof Price) {
            throw ValidationException::withMessages([
                'items' => __('sales::order.no_price', ['id' => $variant->id]),
            ]);
        }

        return [ProductVariant::class, $price->price, CostSource::Fifo];
    }

    /**
     * Yakuniy summani hisoblab, chegirma ruxsatini tekshiradi.
     *
     * Yaxlitlash (§15 #19) faqat **yakuniy** summaga qo'llanadi va farq
     * `rounding` ga yoziladi — satrlarni yaxlitlasak, yig'indi chekdagi
     * summa bilan bir necha so'mga farq qilib qolardi.
     */
    private function applyTotals(User $author, Order $order, Money $subtotal, Money $discount): void
    {
        if ($discount->greaterThan($subtotal)) {
            throw ValidationException::withMessages([
                'discount' => __('sales::order.discount_exceeds_total'),
            ]);
        }

        $net = $subtotal->minus($discount);
        $total = $net->roundToStep();

        $order->update([
            'subtotal' => $subtotal->toString(),
            'discount' => $discount->toString(),
            'rounding' => $total->minus($net)->toString(),
            'total' => $total->toString(),
            'debt' => $total->toString(),
            'discount_approved_by' => $this->approverFor($author, $subtotal, $discount),
        ]);
    }

    /**
     * Chegirma ruxsati — PERMISSIONS.md §4.
     *
     * Tekshiruv Policy'da emas, shu yerda: limit foizini bilish uchun
     * satrlar hisoblanib bo'lgan bo'lishi kerak. Limitdan yuqori
     * chegirmani faqat direktor bera oladi va uning `id` si hujjatga
     * yoziladi — keyin "buni kim ruxsat berdi" degan savolga javob
     * bo'lsin.
     */
    private function approverFor(User $author, Money $subtotal, Money $discount): ?int
    {
        if (! $discount->isPositive()) {
            return null;
        }

        if (! $author->can('sales.discount.apply')) {
            throw ValidationException::withMessages([
                'discount' => __('sales::order.discount_not_allowed'),
            ]);
        }

        // Limit direktor o'zgartira oladigan sozlama (SCHEMA.md §1) —
        // `config()` to'g'ridan-to'g'ri o'qilsa, interfeysdan qo'yilgan
        // qiymat e'tiborsiz qolardi.
        $limit = (int) Setting::valueFor(SettingKey::DiscountLimitPercent);

        if ($subtotal->isZero() || ! $discount->greaterThan($subtotal->percentage($limit))) {
            return null;
        }

        if (! $author->can('sales.discount.approve')) {
            throw ValidationException::withMessages([
                'discount' => __('sales::order.discount_above_limit', ['limit' => $limit]),
            ]);
        }

        return $author->id;
    }

    /**
     * Retsept bo'yicha buyurtma ochilsa, sotuvchidagi tiket yopiladi
     * (7.11) — uning vazifasi "bu retsept bo'yicha ish bor" deb turish
     * edi va u bajarildi.
     *
     * Faqat **o'z filialining** tiketi yopiladi: mijoz boshqa filialga
     * kelib eski retsepti bo'yicha buyurtma bersa, asl filialdagi ish
     * hali bajarilmagan va uning tiketi ochiq qoladi.
     */
    private function closeTicketFor(Order $order, int $branchId): void
    {
        if ($order->prescription_id === null) {
            return;
        }

        $prescription = Prescription::query()->find($order->prescription_id);

        if ($prescription instanceof Prescription) {
            $this->closeTicket->handle($prescription, $branchId);
        }
    }

    /**
     * Chek ochiq smenaga bog'lanadi — kunlik hisobot kalendar sanasi
     * bo'yicha emas, smena bo'yicha yig'iladi (§15 #24).
     */
    private function openShiftId(int $branchId): ?int
    {
        $shift = Shift::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->active()
            ->latest('opened_at')
            ->first();

        return $shift?->id;
    }
}
