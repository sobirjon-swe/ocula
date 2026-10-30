<?php

declare(strict_types=1);

return [
    'field_cash_requires_refund' => 'Field cash was already collected or cannot be identified safely. Use an audited cash refund and driver reconciliation.',
    'amount_must_be_positive' => 'The payment amount must be positive.',
    'order_cancelled' => 'A cancelled order does not accept payments.',
    'exceeds_remaining' => 'The payment exceeds the balance due: :remaining remaining.',
    'already_reversed' => 'This payment has already been reversed.',
    'reverse_of_reversal' => 'A reversal entry cannot be reversed again.',
];
