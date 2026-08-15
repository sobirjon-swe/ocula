<?php

declare(strict_types=1);

return [
    'not_open' => 'This work order is not open (status: :status).',
    'not_startable' => 'This work cannot be started (status: :status).',
    'not_finishable' => 'Only work under way can be finished (status: :status).',
    'not_cancellable' => 'Only a queued work order can be cancelled (status: :status).',
    'not_defective' => 'A defect cannot be recorded in this status (status: :status).',
    'already_consumed' => 'This material has already been consumed.',
    'defect_quantity' => 'The defect quantity exceeds the line: :max at most.',
    'branch_has_no_warehouse' => 'This branch has no warehouse configured.',
];
