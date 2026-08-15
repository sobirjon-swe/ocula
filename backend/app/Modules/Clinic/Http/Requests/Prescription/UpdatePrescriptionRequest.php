<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Requests\Prescription;

/**
 * Retseptni tahrirlash — PERMISSIONS.md §5.
 *
 * Mijoz, filial va vizit **o'zgarmaydi**: ular retseptning kimga va
 * qayerda yozilganini belgilaydi, ularni almashtirish tahrir emas,
 * boshqa hujjat yasash bo'lardi. Shuning uchun bu yerda faqat tibbiy
 * qism qoladi.
 */
class UpdatePrescriptionRequest extends PrescriptionRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['customer_id'], $rules['branch_id'], $rules['visit_id']);

        return $rules;
    }
}
