<?php

namespace App\Http\Middleware;

use App\Services\BintangOtomatis;
use App\Services\Pemberitahuan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengecekan ringan setiap halaman dibuka (B4): status bintang tetap akurat walaupun scheduler terlambat.
 * Sekalian mengirim email piket harian minggu ini yang belum terkirim. Keduanya hanya satu query
 * kalau tidak ada yang perlu diproses. Kalau gagal, halaman tetap dibuka (galatnya dilaporkan).
 */
class PeriksaTenggat
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            try {
                app(BintangOtomatis::class)->proses();
                app(Pemberitahuan::class)->harianMingguIni();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $next($request);
    }
}
