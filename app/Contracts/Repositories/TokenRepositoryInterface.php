<?php

namespace App\Contracts\Repositories;

use App\Models\User;

interface TokenRepositoryInterface
{
    public function createEmailVerificationToken(User $user, string $token) : void;

    public function createPasswordResetToken(User $user, string $token): void;

    public function verifyEmailToken(string $token): ?User;

    public function verifyPasswordResetToken(string $token): ?User;
}
