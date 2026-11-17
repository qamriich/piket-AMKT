<?php

use Illuminate\Support\Facades\Schedule;

// B4 + bagian 8: bintang otomatis, email piket harian, dan pengingat. Di lokal: `php artisan schedule:work`.
// Di hosting tanpa cron (Vercel), route /cron/tick menjalankan schedule:run (Tahap 4).
Schedule::command('piket:jalankan')->everyFifteenMinutes()->withoutOverlapping();
