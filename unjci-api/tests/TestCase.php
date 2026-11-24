<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['membership.requests_closed' => false]);
        \Illuminate\Support\Facades\Storage::fake('local');
    }
}
