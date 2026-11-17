<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya pengurus (role selain warga) yang boleh lewat.
 */
class EnsurePengurus
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPengurus(), 403, 'Halaman ini hanya untuk pengurus.');

        return $next($request);
    }
}
