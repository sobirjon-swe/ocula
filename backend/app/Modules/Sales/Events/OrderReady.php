<?php

declare(strict_types=1);

namespace App\Modules\Sales\Events;

use App\Modules\Sales\Models\Order;

/**
 * Buyurtma `ready` holatiga o'tdi — PROJECT.md 7.3, BOSQICH-7.md §4.1.
 *
 * Loyihadagi birinchi domen hodisasi (PROJECT.md §9). `ChangeOrderStatus`
 * bu hodisani kim tinglashini bilmaydi — Telegram moduli mijozga xabar
 * yuborish uchun tinglaydi (`TelegramServiceProvider`).
 */
final class OrderReady
{
    public function __construct(public readonly Order $order) {}
}
