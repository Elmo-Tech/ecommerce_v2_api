<?php

use Tests\TestCase;

// Bind only Feature tests to the Laravel application test case.
// No database refresh or migration traits are registered in F0-01.
pest()->extend(TestCase::class)->in('Feature');
