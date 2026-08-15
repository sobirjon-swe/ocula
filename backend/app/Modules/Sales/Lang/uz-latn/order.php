<?php

declare(strict_types=1);

return [
    'no_items' => "Chekda birorta satr yo'q — avval tovar yoki xizmat qo'shing.",
    'no_price' => "Bu tovarga narx qo'yilmagan (variant :id). Avval narxni kiriting.",
    'discount_exceeds_line' => "Chegirma satr summasidan katta bo'la olmaydi.",
    'discount_exceeds_total' => "Chegirma chek summasidan katta bo'la olmaydi.",
    'discount_not_allowed' => "Sizda chegirma berish huquqi yo'q.",
    'discount_above_limit' => 'Chegirma :limit% dan oshdi — buni faqat direktor tasdiqlay oladi.',
    'not_deliverable' => "Bu buyurtmani hozir topshirib bo'lmaydi (holati: :status).",
    'not_cancellable' => "Bu buyurtmani bekor qilib bo'lmaydi (holati: :status). "
        .'Topshirilgan tovar uchun qaytarish rasmiylashtiriladi.',
    'cancel_with_payment' => "Buyurtma bo'yicha pul olingan — avval to'lovni storno qiling, keyin bekor qiling.",
    'transition_not_allowed' => "':from' holatidan ':to' ga o'tib bo'lmaydi. Mumkin: :allowed.",
    'branch_has_no_warehouse' => "Bu filialda ombor sozlanmagan — tovarni chiqarib bo'lmaydi.",
];
