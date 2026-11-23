<?php

/*
 * Pintu masuk di Vercel (runtime vercel-php). Semua request selain file statis diarahkan ke sini (lihat vercel.json).
 *
 * Di Vercel hanya /tmp yang bisa ditulis, jadi folder storage (view hasil kompilasi, log, file sementara ekspor Excel)
 * dan cache bootstrap (config, route, package) dipindah ke /tmp. Sesi, cache aplikasi, dan antrean ada di database.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$storage = '/tmp/storage';
foreach (['app', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $folder) {
    if (! is_dir("$storage/$folder")) {
        @mkdir("$storage/$folder", 0755, true);
    }
}

foreach ([
    'APP_CONFIG_CACHE' => '/tmp/config.php',
    'APP_EVENTS_CACHE' => '/tmp/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/services.php',
    'VIEW_COMPILED_PATH' => "$storage/framework/views",
] as $kunci => $nilai) {
    putenv("$kunci=$nilai");
    $_ENV[$kunci] = $_SERVER[$kunci] = $nilai;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->useStoragePath($storage);

$app->handleRequest(Request::capture());
