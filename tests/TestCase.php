<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /**
         * Sanctum only makes an API request stateful when it can see an
         * `Origin`/`Referer` from a configured first-party domain, and
         * without a stateful request there is no session for the cookie
         * guard to write to. A real SPA always sends one, so the test client
         * does too — otherwise login/logout would be untestable and the
         * failure would look like a bug in AuthController (02 §1).
         */
        $this->withHeader('Referer', 'http://localhost');
    }
}
