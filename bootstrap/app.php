<?php

use App\Http\Controllers\CronTickController;
use App\Http\Middleware\EnsureAkunAktif;
use App\Http\Middleware\EnsurePengurus;
use App\Http\Middleware\PeriksaTenggat;
use App\Http\Middleware\WajibGantiPassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // /cron/tick di luar grup web: tanpa sesi, cookie, dan CSRF (dipanggil cron eksternal, bukan browser).
        then: function () {
            Route::get('/cron/tick', CronTickController::class)->middleware('throttle:10,1')->name('cron.tick');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Di Vercel aplikasi berada di belakang proxy; header X-Forwarded-* dipercaya supaya URL dan cookie memakai https.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'pengurus' => EnsurePengurus::class,
        ]);

        $middleware->web(append: [
            EnsureAkunAktif::class,
            WajibGantiPassword::class,
            PeriksaTenggat::class,
        ]);

        // Tamu ke halaman login; yang sudah login (misalnya membuka /login lagi) ke halaman sesuai role.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->halamanAwal() ?? route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
