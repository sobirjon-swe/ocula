<?php

declare(strict_types=1);

return [
    'no_items' => 'The transfer has no lines — add a product first.',
    'same_location' => 'Goods cannot be moved to the place they already are.',
    'same_branch' => 'A transfer goes between two different branches.',
    'not_draft' => 'This transfer has already been sent or cancelled.',
    'not_on_the_road' => 'This transfer is not on the road — it cannot be received.',
    'cancel_only_draft' => 'Only a draft can be cancelled. '
        .'Sent goods are received, and whatever is missing gets written off.',
    'carrier_required' => 'Name who is carrying it — goods on the road must have someone responsible.',
    'taxi_cost_required' => 'Taxi was chosen — enter the cost.',
    'branch_has_no_warehouse' => 'Branch ":name" has no warehouse configured.',
    'item_missing_in_count' => 'Enter the actual quantity for every line — one is missing.',
    'received_more_than_sent' => 'More cannot be received than was sent: :sent were sent.',
    'no_discrepancy' => 'This transfer has no discrepancy to resolve.',
    'write_off_reason' => 'Transfer :number — the part that never arrived',
    'transit_taxi' => 'On the road (taxi) — :number',
    'transit_person' => 'On the road — :name',
    'discrepancy_resolved' => 'Discrepancy resolved (:name): :reason',
];
