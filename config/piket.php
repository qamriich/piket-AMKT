<?php

return [
    // Secret untuk route /cron/tick (Tahap 4).
    'cron_secret' => env('CRON_SECRET'),

    // Password sementara untuk semua akun hasil seeder, HANYA di lingkungan lokal (untuk dicoba).
    'password_lokal' => env('PIKET_PASSWORD_LOKAL', 'piket123'),
];
