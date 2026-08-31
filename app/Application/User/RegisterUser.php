<?php

namespace App\Application\User;

use App\Domain\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function handle(array $attributes): User
    {
        $user = new User([
            'name' => $attributes['name'],
            'email' => Str::lower($attributes['email']),
            'password' => $attributes['password'],
        ]);
        $user->role = UserRole::Customer;
        $user->save();

        return $user;
    }
}
