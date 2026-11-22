<?php

return [
    // Secret untuk route /cron/tick (Tahap 4).
    'cron_secret' => env('CRON_SECRET'),

    // Batas waktu memproses antrean email per tick, di bawah batas waktu request hosting (Vercel Hobby: 60 detik).
    'cron_maks_detik' => (int) env('CRON_MAKS_DETIK', 40),

    // Password sementara untuk semua akun hasil seeder, HANYA di lingkungan lokal (untuk dicoba).
    'password_lokal' => env('PIKET_PASSWORD_LOKAL', 'piket123'),
];
