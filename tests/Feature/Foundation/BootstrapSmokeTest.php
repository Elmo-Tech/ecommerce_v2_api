<?php

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Route;

it('boots the isolated foundation without accessing a database', function (): void {
    /** @var \Tests\TestCase $this */

    expect(config('app.env'))->toBe('testing')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync');

    /** @var DatabaseManager $database */
    $database = app('db');

    expect($database->getConnections())->toBeEmpty();

    // Laravel 13's stock local-disk "serve" flag enables HTTP GET/PUT storage
    // routes unless disabled. F0-01 does not include file serving.
    expect(Route::has('storage.local'))->toBeFalse()
        ->and(Route::has('storage.local.upload'))->toBeFalse();

    $this->get('/__f0_unregistered_path')->assertNotFound();

    expect($database->getConnections())->toBeEmpty();
})->group('foundation');
