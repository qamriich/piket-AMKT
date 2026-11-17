<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PRD bagian 15 R2: selama password awal belum diganti, semua halaman (termasuk aksi Livewire)
 * diarahkan ke halaman "Buat password baru". Yang boleh hanya halaman itu sendiri dan keluar.
 */
class WajibGantiPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->harus_ganti_password && ! $request->routeIs('password.baru', 'password.baru.simpan', 'keluar')) {
            if ($request->hasHeader('X-Livewire')) {
                abort(403, 'Buat password baru dulu.');
            }

            return redirect()->route('password.baru');
        }

        return $next($request);
    }
}
