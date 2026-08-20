<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

class RegisterUserAction
{
    /**
     * FR-AUTH-01: creating the account fires `Registered`, which is what
     * queues the verification mail through Laravel's built-in listener.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): User
    {
        $user = DB::transaction(fn (): User => User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
            'timezone' => $attributes['timezone'] ?? (string) config('pathforge.default_timezone'),
        ]));

        event(new Registered($user));

        return $user;
    }
}
