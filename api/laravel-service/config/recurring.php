<?php

return [
    'max_failures' => (int) env('RECURRING_MAX_FAILURES', 3),
    'batch_size' => (int) env('RECURRING_BATCH_SIZE', 50),
    'lock_seconds' => (int) env('RECURRING_LOCK_SECONDS', 120),
    'retry_delay_minutes' => (int) env('RECURRING_RETRY_DELAY_MINUTES', 60),
];
