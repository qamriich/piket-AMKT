<?php

namespace App\Providers;

use App\Http\Middleware\EnsurePengurus;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
        CarbonImmutable::setLocale(config('app.locale'));

        Model::preventLazyLoading(! $this->app->isProduction());

        // Aksi Livewire (tanpa reload) dari halaman pengurus tetap dicek role-nya.
        Livewire::addPersistentMiddleware([
            EnsurePengurus::class,
        ]);
    }
}
