<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CSRF is irrelevant for feature logic assertions in tests.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
