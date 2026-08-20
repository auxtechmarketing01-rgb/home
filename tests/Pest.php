<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Both suites hit the database: Feature tests go through the HTTP layer and
| Sanctum's real guard, while the Unit suite exercises Actions, Services and
| Jobs directly against Eloquent (06 §1.1, §1.3).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');
