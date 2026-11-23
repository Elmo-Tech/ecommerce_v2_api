<?php

use Illuminate\Database\DatabaseManager;

it('boots the isolated foundation without accessing a database', function (): void {
    expect(config('app.env'))->toBe('testing')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync');

    /** @var DatabaseManager $database */
    $database = app('db');

    expect($database->getConnections())->toBeEmpty();

    $this->get('/__f0_unregistered_path')->assertNotFound();

    expect($database->getConnections())->toBeEmpty();
})->group('foundation');
