<?php

namespace App\Modules\FoundationDemo;

use App\Modules\FoundationDemo\Contracts\FoundationMarker;
use Illuminate\Support\ServiceProvider;

final class FoundationDemoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FoundationMarker::class, static fn (): FoundationMarker => new FoundationMarker());
    }

    public function boot(): void
    {
        // The probe is NEVER registered when the app boots as non-testing.
        // Production route caches must be built under APP_ENV=production.
        if ($this->app->environment('testing')) {
            $this->loadRoutesFrom(__DIR__.'/Routes/testing.php');
        }
    }
}
