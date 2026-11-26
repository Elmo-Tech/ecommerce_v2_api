<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** The application owned by this booted Laravel feature-test instance. */
    public function foundationApplication(): Application
    {
        return $this->app;
    }
}
