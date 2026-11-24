<?php

namespace Tests\Fixtures;

use Illuminate\Support\ServiceProvider;

/**
 * Test-only abstract provider used to prove full registry validation is atomic.
 */
abstract class AbstractModuleProvider extends ServiceProvider
{
}
