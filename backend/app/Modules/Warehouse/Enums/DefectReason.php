<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Enums;

/**
 * Brak sababi — ENUMS.md §3, PROJECT.md 7.5.
 *
 * "Usta faqat sababni tanlaydi, qolganini tizim qiladi." Sabab ikkita
 * savolga javob beradi: **kim to'laydi** va **keyin nima bo'ladi**.
 *
 * | Sabab | Kim to'laydi | Tizimda |
 * |---|---|---|
 * | `supplier_defect` | Yetkazib beruvchi | Qaytarish akti + `supplier_transactions` |
 * | `transport_damage` | Haydovchi/yetkazuvchi | Yo'l varaqasiga bog'lanadi |
 * | `master_error` | Do'kon zarari | Usta statistikasiga, mukofotdan ayiriladi (7.12) |
 * | `customer_request` | Mijoz yoki do'kon | Buyurtma `rework` ga qaytadi |
 */
enum DefectReason: string
{
    case SupplierDefect = 'supplier_defect';
    case TransportDamage = 'transport_damage';
    case MasterError = 'master_error';
    case CustomerRequest = 'customer_request';

    /**
     * Buyurtma qayta ishlashga qaytadimi (7.5).
     */
    public function reopensOrder(): bool
    {
        return $this === self::CustomerRequest;
    }

    /**
     * Zarar do'kon zimmasidami — mukofot hisobiga ta'sir qiladi (7.12).
     */
    public function isOnTheShop(): bool
    {
        return $this === self::MasterError;
    }

    /**
     * Yetkazib beruvchidan qaytarish akti kerakmi (7.15).
     */
    public function requiresSupplierClaim(): bool
    {
        return $this === self::SupplierDefect;
    }
}
