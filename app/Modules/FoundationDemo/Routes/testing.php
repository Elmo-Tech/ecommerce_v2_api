<?php

use App\Modules\FoundationDemo\Contracts\FoundationMarker;
use Illuminate\Support\Facades\Route;

// Only required by FoundationDemoServiceProvider while APP_ENV=testing.
Route::get('api/v1/__foundation/module-probe', static fn () => response()->json(
    app(FoundationMarker::class)->payload(),
))->name('foundation.demo.probe');
