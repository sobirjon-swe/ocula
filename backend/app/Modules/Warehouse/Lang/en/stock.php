<?php

declare(strict_types=1);

return [
    'not_enough' => 'Cannot issue from stock: :available left, :needed required.',
    'wrong_sign' => 'A ":type" movement cannot go in this direction.',
    'quantity_must_be_positive' => 'Quantity must be positive — the operation itself sets the direction.',
    'reason_required' => 'This operation requires a reason — the report must answer "why".',
    'already_reversed' => 'This movement has already been reversed.',
    'reverse_of_reversal' => 'A reversal cannot be reversed again.',
    'layer_partly_consumed' => 'Stock from this receipt has already been issued — it cannot be reversed. '
        .'Record the difference as an inventory adjustment instead.',
];
