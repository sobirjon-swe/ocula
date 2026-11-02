<?php

return [
    'idempotency_required' => 'Send a unique Idempotency-Key of up to 64 characters for this operation.',
    'idempotency_conflict' => 'This Idempotency-Key belongs to another request. Use a new key for a new operation.',
    'idempotency_processing' => 'The previous request is still processing. Try again in a few seconds.',
];
