<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Laravel 12 ships this class empty. AuthorizesRequests is added here so
 * `$this->authorize()` works in controllers — without it the call is a
 * fatal error, which would mean policy checks silently never ran.
 */
abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
