<?php

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Http\Request;

interface TokenServiceInterface
{
    public function generateAuthToken(User $user): ?string;

    public function generateEmailVerificationToken(User $user): ?string;

    public function generatePasswordResetToken(User $user): ?string;

    public function verifyEmailToken(string $token): ?User;

    public function verifyPasswordResetToken(string $token): ?User;

    public function revokeCurrentToken(Request $request): void;

    public function revokeAllTokens(User $user): void;

}
