<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'pengurus' => \App\Http\Middleware\EnsurePengurus::class,
            'pin' => \App\Http\Middleware\RequirePin::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnsureAkunAktif::class,
        ]);

        // Tidak ada halaman login: tamu diarahkan ke beranda.
        $middleware->redirectGuestsTo(fn () => route('beranda'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
