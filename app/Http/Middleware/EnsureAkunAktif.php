<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Warga yang sudah dinonaktifkan langsung dikeluarkan, walaupun cookie "ingat saya" masih ada.
 */
class EnsureAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->aktif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('beranda')->with('error', 'Akunmu sudah tidak aktif. Hubungi pengurus kalau ini keliru.');
        }

        return $next($request);
    }
}
