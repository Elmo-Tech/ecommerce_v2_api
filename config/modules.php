<?php

use App\Modules\FoundationDemo\FoundationDemoServiceProvider;

// ONLY modules explicitly listed here are registered during bootstrap.
// The FoundationDemo provider is a non-business test fixture.
return [
    [
        'id' => 'foundation-demo',
        'provider' => FoundationDemoServiceProvider::class,
    ],
];
