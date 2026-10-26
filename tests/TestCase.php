<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The reviews are kept in memory for one request; clear them so one test can't affect the next
        \App\Support\Testimonials::flush();
    }
}
