<?php

use Illuminate\Support\Facades\Schedule;

// B4 + bagian 8: bintang otomatis, email piket harian, dan pengingat. Di lokal: `php artisan schedule:work`.
// Di hosting tanpa cron (Vercel), route /cron/tick menjalankan piket:jalankan dan queue:work langsung (CronTickController).
Schedule::command('piket:jalankan')->everyFifteenMinutes()->withoutOverlapping();
