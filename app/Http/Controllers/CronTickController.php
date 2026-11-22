<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * PRD bagian 9: GET /cron/tick?key=<CRON_SECRET>, dipanggil tiap 15 menit dari cron eksternal (misalnya cron-job.org)
 * untuk hosting tanpa cron atau worker.
 *
 * Tugasnya dijalankan langsung di proses ini, bukan lewat `schedule:run`, karena schedule:run membuka proses PHP baru
 * (sering tidak bisa di serverless) dan hanya menjalankan tugas 15 menitan kalau tick jatuh tepat di menit 0/15/30/45.
 * `piket:jalankan` aman dijalankan berulang: setiap bintang dan email hanya diproses sekali.
 */
class CronTickController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('piket.cron_secret');

        // Tanpa secret, route dianggap tidak ada. Pembandingan memakai hash_equals supaya tahan timing attack.
        abort_if($secret === '' || ! hash_equals($secret, (string) $request->query('key')), 404);

        $kunci = Cache::lock('cron-tick', 300);
        if (! $kunci->get()) {
            return response()->json(['ok' => true, 'pesan' => 'Tick sebelumnya masih berjalan.']);
        }

        try {
            Artisan::call('piket:jalankan');
            $piket = trim(Artisan::output());

            // Kirim email yang antre (QUEUE_CONNECTION=database), berhenti saat antrean kosong atau batas waktu tercapai.
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => (int) config('piket.cron_maks_detik'),
                '--tries' => 3,
            ]);

            return response()->json(['ok' => true, 'piket' => $piket]);
        } finally {
            $kunci->release();
        }
    }
}
