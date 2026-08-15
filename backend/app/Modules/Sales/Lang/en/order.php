<?php

declare(strict_types=1);

return [
    'no_items' => 'The receipt has no lines — add a product or a service first.',
    'no_price' => 'This product has no price set (variant :id). Set a price first.',
    'discount_exceeds_line' => 'The discount cannot exceed the line total.',
    'discount_exceeds_total' => 'The discount cannot exceed the order total.',
    'discount_not_allowed' => 'You are not allowed to give a discount.',
    'discount_above_limit' => 'The discount exceeds :limit% — only the director can approve it.',
    'not_deliverable' => 'This order cannot be delivered right now (status: :status).',
    'not_cancellable' => 'This order cannot be cancelled (status: :status). '
        .'Delivered goods go through a return instead.',
    'cancel_with_payment' => 'Money was taken for this order — reverse the payment first, then cancel.',
    'transition_not_allowed' => 'Cannot move from ":from" to ":to". Allowed: :allowed.',
    'branch_has_no_warehouse' => 'This branch has no warehouse configured — goods cannot be issued.',
];
