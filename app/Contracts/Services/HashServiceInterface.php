<?php

namespace App\Contracts\Services;

interface HashServiceInterface
{
    public function make(string $value): string;

    public function verify(string $value, string $hashedValue) : bool;
}
