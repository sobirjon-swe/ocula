# BOSQICH 3 — SAVDO VA KASSA (ish rejasi)

> Manba: `docs/PROJECT.md` §11 (Bosqich 3), 7.3, 7.8, 7.20, 7.21;
> `docs/SCHEMA.md` §4, §8; `docs/ENUMS.md` §4, §8; `docs/PERMISSIONS.md`.
> Sana: 2026-08-14. Holat: **kod yozilmagan**, faqat qarorlar qilingan.
>
> Hujjat maqsadi: hujjatlarni qaytadan titkilamasdan ishni davom ettirish.
> Qarama-qarshilik chiqsa — `PROJECT.md`/`SCHEMA.md` yutadi, bu fayl emas.

---

## 0. Hajm

**Kiradi:** chek va buyurtma, to'lov turlari, chegirma, qaytarish,
kassa daftari, smena yopilishida kutilgan naqdni haqiqiy hisoblash.

**Kirmaydi (Finance bosqichiga qoladi):** `debts`, `expenses`,
`expense_categories`, `supplier_transactions`, qarz eslatmalari (7.6, 7.15).
`orders.payment_status = debt` qiymati enumda bor, lekin uni qo'yadigan
mexanizm (muddat o'tishi) keyingi bosqichda yoziladi.

---

## 1. Migratsiyalar

Markazlashgan `database/migrations/` da (SCHEMA.md §12), raqamlash
Bosqich 2 dan keyin davom etadi:

| Fayl | Jadvallar | Manba |
|---|---|---|
| `0001_01_01_000300_create_customers_table.php` | `customers` | SCHEMA §4 |
| `0001_01_01_000310_create_orders_table.php` | `orders`, `order_items` | SCHEMA §4 |
| `0001_01_01_000320_create_payments_table.php` | `payments` | SCHEMA §4 |
| `0001_01_01_000330_create_returns_table.php` | `returns`, `return_items` | SCHEMA §4 |
| `0001_01_01_000340_create_cash_movements_table.php` | `cash_movements` | SCHEMA §8 |

Ustunlar SCHEMA.md dan **bir chetga og'ishmasdan** olinadi. Eslatmalar:

- `payments` va `cash_movements` — **insert-only** (SCHEMA §0): `updated_at`
  ustuni yo'q, `reverses_id` + `reason` bor, modelda `Immutable` trait.
- `orders.revenue_recognized_at` — foyda hisoboti shu ustun bo'yicha (3.5, 7.8),
  `created_at` bo'yicha emas.
- `order_items` polimorf: `itemable_type` (`ProductVariant` | `Service`),
  `cost_source` (`fifo` | `manual` | `none`), `movement_id` → `stock_movements`.
- Indekslar ham SCHEMA da yozilgan holicha (`orders_obligation_idx` va
  boshqalar) — ular hisobotlar uchun.

---

## 2. Modullar va fayllar

Namuna — **`app/Modules/Warehouse`**. Undan og'ish qilinmaydi: `Actions/` da
biznes mantiq, kontrollerda faqat `authorize()` + Action chaqirig'i,
ro'yxatlarda `spatie/laravel-query-builder`, har modulda
`Lang/{uz-latn,ru,en}`, `Routes/api.php`.

### Sales

```
Enums/      OrderType, OrderStatus, PaymentStatus, OrderDeliveryType,
            PaymentMethod, PaymentTxStatus, ReturnReason, CostSource
Models/     Customer, Order, OrderItem, Payment, OrderReturn, OrderReturnItem
Actions/    Order\CreateOrder, Order\DeliverOrder, Order\ChangeOrderStatus,
            Order\CancelOrder, Payment\RecordPayment, Payment\ReversePayment,
            OrderReturn\CreateReturn
Policies/   OrderPolicy, CustomerPolicy, PaymentPolicy, OrderReturnPolicy
Http/       Controllers, Requests, Resources
Lang/       order.php, payment.php, return.php, customer.php
```

> `returns` jadvali uchun model nomi **`OrderReturn`** — `Return` PHP kalit
> so'zi, klass shunday nomlanmaydi. Modelda `protected $table = 'returns'`.

### Finance

```
Enums/      CashDirection, CashCategory
Models/     CashMovement            (Immutable)
Services/   CashRegister            (App\Support\Contracts\CashLedger implementatsiyasi)
Policies/   CashMovementPolicy
Http/       CashController + Request/Resource
Lang/       cash.php
```

Ikkala modulning ham `ServiceProvider` i allaqachon bor va
`bootstrap/providers.php` da ro'yxatdan o'tgan — faqat `policies()` to'ldiriladi.

---

## 3. Qaror qilingan mantiq

### 3.1 Tovar qachon ombordan chiqadi

**`deliver` paytida**, ikkala tur uchun ham bitta yo'l (`DeliverOrder`):

- `type = order` → alohida `POST /orders/{id}/deliver`;
- `type = quick` → `CreateOrder` shu amalni **o'zi darrov chaqiradi**
  (chek tovar berilganda tug'iladi, ENUMS §4: `new → delivered → closed`).

O'sha paytda `revenue_recognized_at` qo'yiladi (7.8: "kechagi buyurtma bugun
topshirilsa — daromad bugunga").

Chiqim faqat `StockLedger::issue()` orqali, `MovementType::Sale`, hujjat
sifatida `Order` beriladi. Ombor holatiga to'g'ridan-to'g'ri tegilmaydi.

### 3.2 Tannarx (COGS)

`StockLedger::issue()` qaytargan harakatning `cost_total` i satrga yoziladi:

| Satr turi | `cost_source` | Tannarx manbai |
|---|---|---|
| Ombordagi variant | `fifo` | Sarflangan FIFO qatlamlari (7.20) |
| Individual linza | `manual` | Qo'lda kiritiladi yoki `purchase_item_id` (ANALIZ 3.9) |
| Xizmat | `none` | Tannarx yo'q — hisobotda "tannarxsiz" qatorida |

`orders.cost_total` — satrlar yig'indisi.

### 3.3 Kassa daftari

`App\Modules\Finance\Services\CashRegister` — **`cash_movements` ga
yozadigan yagona joy** (`StockLedger` ning ko'zgusi, 7.21).

Core moduli Finance ga bog'lanib qolmasligi uchun:

```
App\Support\Contracts\CashLedger      ← interfeys (Support qatlamida)
App\Modules\Finance\Services\CashRegister   ← implementatsiya
FinanceServiceProvider::register()    ← binding
```

`OpenShift` va `CloseShift` **interfeysga** bog'lanadi, Finance klassiga emas.

### 3.4 Smena va kutilgan naqd

```
expectedCash(Shift) = shift.opening_cash
                    + Σ(ishorali harakatlar, category != shift_opening)
```

`shift_opening` ataylab chiqarib tashlanadi — aks holda boshlang'ich naqd
ikki marta sanaladi.

- `OpenShift`: boshlang'ich naqd musbat bo'lsa `shift_opening` (in) yoziladi.
- `CloseShift`: farq bo'lsa `shortage` (out) yoki `surplus` (in) yoziladi —
  sanoqdan keyin daftar seyfdagi haqiqiy pul bilan mos keladi.

> **Diqqat:** mavjud `tests/Feature/Core/ShiftApiTest.php` factory bilan
> yasalgan (harakatsiz) smenada `expected_cash == opening_cash` ni kutadi.
> Yuqoridagi formula o'sha testlarni buzmaydi — buzilsa, formula noto'g'ri
> yozilgan.

### 3.5 To'lov

- Insert-only. Muvaffaqiyatsiz to'lov umuman yozilmaydi (ENUMS §4).
- **Faqat `method = cash`** kassaga tushadi → `cash_movements`
  (`sale` yoki `debt_payment`). Karta, o'tkazma, click, payme — tushmaydi.
- To'lovdan keyin `orders.paid` / `debt` / `payment_status` qayta hisoblanadi
  (`unpaid → partial → paid`).
- Storno (`sales.payment.reverse`, faqat direktor): manfiy `payment` +
  teskari kassa yozuvi, ikkalasi ham `reverses_id` bilan aslga bog'lanadi.

### 3.6 Qaytarish

- `restock = true` → `StockLedger::receive(MovementType::Return)`, birlik
  tannarx sifatida **sotilgandagi** tannarx (SCHEMA §4 izohi).
  `restock = false` → brak; `defects` jadvali Bosqich 6 da, shu sababli
  hozircha faqat ombor harakatisiz yoziladi va izohda belgilanadi.
- Pul qaytsa: manfiy `payment` + `refund` (out) kassa yozuvi.
- To'liq qaytarilganda `orders.status = returned`, `payment_status = refunded`.

### 3.7 Boshqa mayda qarorlar

- `Customer` **`BelongsToBranch` ni ishlatmaydi** — mijoz butun tarmoqniki,
  `branch_id` faqat "birinchi kelgan filial" (SCHEMA §4). `Order`, `Payment`,
  `OrderReturn`, `CashMovement` esa ishlatadi.
- Yakuniy `total` `Money::roundToStep()` (100 so'm) bilan yaxlitlanadi,
  farq `orders.rounding` ga (§15 #19).
- Chegirma: `sales.discount.apply` limitgacha, undan yuqorisi
  `sales.discount.approve` (direktor) — `discount_approved_by` ga yoziladi.
- Ruxsatlar **allaqachon seed qilingan** (`RolePermissionSeeder` da `sales.*`
  va `finance.*` to'liq) — faqat Policy va Controller yoziladi.
- Ombor/kassaga tegadigan barcha `POST` lar `idempotency` middleware ostida
  (§9): buyurtma yaratish, topshirish, to'lov, qaytarish, kassa yozuvi.

---

## 4. API (rejalashtirilgan)

```
GET    /customers            POST /customers        PUT /customers/{id}
GET    /customers/{id}

GET    /orders               POST /orders
GET    /orders/{id}
POST   /orders/{id}/deliver          — tovar chiqadi, daromad tan olinadi
POST   /orders/{id}/status           — holat o'qi (7.3)
POST   /orders/{id}/cancel

POST   /orders/{id}/payments         — to'lov qabul qilish
POST   /payments/{id}/reverse        — storno (direktor)

POST   /orders/{id}/returns          — qaytarish
GET    /returns

GET    /cash/movements               — kassa daftari
GET    /cash/summary                 — joriy smena bo'yicha kassa holati
POST   /cash/movements               — qo'lda kirim/chiqim
POST   /cash/movements/{id}/reverse  — storno (direktor)
```

---

## 5. Testlar (yozilishi shart)

| Fayl | Kafolat |
|---|---|
| `Feature/Sales/OrderApiTest.php` | Tez savdo omborni kamaytiradi va FIFO tannarxini satrga yozadi; qoldiq yetmasa 422; chegirma va yaxlitlash; filial izolyatsiyasi; `Idempotency-Key` |
| `Feature/Sales/PaymentApiTest.php` | `unpaid → partial → paid`; naqd kassa yozuvini tug'diradi, karta yo'q; storno ikkalasini teskari qiladi |
| `Feature/Sales/ReturnApiTest.php` | `restock` omborga qaytaradi; pul `refund` bo'lib chiqadi; buyurtma holati `returned` |
| `Feature/Finance/CashRegisterTest.php` | `expectedCash` formulasi; smena yopilishida `shortage`/`surplus` yoziladi; `cash_movements` tahrirlanmaydi (`ImmutableRecordException`) |

Mavjud `ShiftApiTest` **yashil qolishi shart** (3.4 dagi ogohlantirish).

Yakunda `composer ci:check` to'liq yashil bo'lishi kerak: Pint, PHPStan
level 7 — 0 xato, barcha testlar.

---

## 6. Bosqich 3 dan keyin

1. `Device` CRUD (`core.device.*`) va `Setting` CRUD (`core.settings.*`) —
   Bosqich 1 ro'yxatidan qolgan.
2. Admin SPA ekranlari: kassa/chek, filiallar, xodimlar, smena, kirim hujjati.
3. Bosqich 4 — transfer (`MovementType` da turlari va `StockLedger` tayyor).
