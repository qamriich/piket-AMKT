<?php

return [
    // Menit tanpa aktivitas di menu pengurus sebelum PIN diminta lagi.
    'pin_idle_minutes' => (int) env('PENGURUS_PIN_IDLE_MINUTES', 120),

    // Secret untuk route /cron/tick (Tahap 4).
    'cron_secret' => env('CRON_SECRET'),
];
