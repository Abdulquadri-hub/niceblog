<?php

namespace App\Services\Auth;

use App\Contracts\Services\HashServiceInterface;
use Illuminate\Support\Facades\Hash;

class HashService implements HashServiceInterface
{

    public function make(string $value): string
    {
        return Hash::make($value);
    }

    public function verify(string $value, string $hashedValue): bool
    {
        return Hash::check($value, $hashedValue);
    }

}
