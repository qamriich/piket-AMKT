<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PIN pengurus (bagian 2 poin 4).
 *
 * - Belum punya PIN: wajib membuat PIN dulu.
 * - Sudah punya PIN: diminta setiap sesi baru. Sesi baru = browser ditutup
 *   (cookie sesi kedaluwarsa) atau tidak ada aktivitas di menu pengurus selama
 *   PENGURUS_PIN_IDLE_MINUTES menit (default 120).
 */
class RequirePin
{
    public const SESSION_KEY = 'pin_terverifikasi_pada';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->punyaPin()) {
            $this->simpanTujuan($request);

            return redirect()->route('pin.buat');
        }

        if (! self::masihBerlaku($request)) {
            $request->session()->forget(self::SESSION_KEY);
            $this->simpanTujuan($request);

            return redirect()->route('pin.masukkan');
        }

        // Batas idle bergeser setiap ada aktivitas.
        self::tandaiTerverifikasi($request);

        return $next($request);
    }

    public static function masihBerlaku(Request $request): bool
    {
        $waktu = $request->session()->get(self::SESSION_KEY);
        $batasDetik = (int) config('piket.pin_idle_minutes', 120) * 60;

        return $waktu !== null && (now()->getTimestamp() - (int) $waktu) <= $batasDetik;
    }

    public static function tandaiTerverifikasi(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->getTimestamp());
    }

    private function simpanTujuan(Request $request): void
    {
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }
    }
}
