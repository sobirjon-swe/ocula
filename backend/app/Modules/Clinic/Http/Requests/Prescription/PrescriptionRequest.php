<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Retsept — SCHEMA.md §5, PROJECT.md §10 (shifokor ekrani).
 *
 * Diopterlar **0.25 qadamli**: bu "−2.5 o'rniga −25" xatosining oldini
 * oladi. Interfeys ro'yxatdan tanlatadi, API esa qadamni o'zi
 * tekshiradi — tekshiruv faqat ekranda qolsa, boshqa mijoz (mobil,
 * import) uni chetlab o'tardi.
 *
 * AXIS 0–180: bundan tashqarisi burchak sifatida ma'nosiz.
 */
class PrescriptionRequest extends FormRequest
{
    /** Diopter chegarasi — `decimal(4,2)` sig'imi va tibbiy ma'no. */
    private const string DIOPTER_MIN = '-30';

    private const string DIOPTER_MAX = '30';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $diopter = [
            'nullable', 'numeric',
            'min:'.self::DIOPTER_MIN, 'max:'.self::DIOPTER_MAX,
            'multiple_of:0.25',
        ];

        $axis = ['nullable', 'integer', 'min:0', 'max:180'];

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],

            'od_sph' => $diopter,
            'od_cyl' => $diopter,
            'od_axis' => $axis,
            'od_add' => $diopter,

            'os_sph' => $diopter,
            'os_cyl' => $diopter,
            'os_axis' => $axis,
            'os_add' => $diopter,

            // Ko'z qorachiqlari orasidagi masofa — millimetrda, 0.5 qadam.
            'pd' => ['nullable', 'numeric', 'min:40', 'max:90', 'multiple_of:0.5'],
            'pd_near' => ['nullable', 'numeric', 'min:40', 'max:90', 'multiple_of:0.5'],

            'prism' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Retseptning tibbiy qismi — Action shu maydonlarni oladi.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->only([
            'od_sph', 'od_cyl', 'od_axis', 'od_add',
            'os_sph', 'os_cyl', 'os_axis', 'os_add',
            'pd', 'pd_near', 'prism', 'notes',
        ]);
    }
}
