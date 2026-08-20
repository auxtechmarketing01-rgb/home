<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Every mutating endpoint runs a Policy ability (01 §5 Security), so the
     * base controller carries the trait that exposes `authorize()`.
     */
    use AuthorizesRequests;
}
