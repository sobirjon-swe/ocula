<?php

declare(strict_types=1);

return [
    'already_has_trip' => 'This driver already has a trip on :date.',
    'stops_only_while_planned' => 'Stops can only be added before the trip starts.',
    'transfer_required' => 'A transfer must be selected for a branch stop.',
    'transfer_wrong_driver' => 'This transfer was sent with a different method or driver.',
    'transfer_not_sent' => 'This transfer has not been sent yet.',
    'transfer_already_on_trip' => 'This transfer is already linked to another trip.',
    'order_required' => 'An order must be selected for a customer stop.',
    'order_has_no_customer' => 'This order has no customer set.',
    'address_required' => 'An address is required.',
    'cannot_start' => 'Cannot start the trip: current status is :status.',
    'cannot_finish' => 'Cannot finish the trip: current status is :status.',
    'cannot_cancel' => 'Cannot cancel the trip: current status is :status.',
];
