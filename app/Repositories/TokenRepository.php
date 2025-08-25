<?php

namespace App\Repositories;

use App\Contracts\Repositories\TokenRepositoryInterface;
use App\Models\EmailVerificationsToken;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TokenRepository implements TokenRepositoryInterface
{

    public function createEmailVerificationToken(User $user, string $token): void {
        EmailVerificationsToken::insert([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'created_at' => now()
        ]);
    }

    public function createPasswordResetToken(User $user, string $token): void
    {
        PasswordResetToken::insert([
            'email' => $user->email,
            'token' => $token,
            'created_at' => now()
        ]);
    }

    public function verifyEmailToken(string $token): ?User
    {
        $record = EmailVerificationsToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if(!$record) {
            return null;
        }

        EmailVerificationsToken::where('user_id', $record->user_id)->delete();

        return User::find($record->user_id);
    }

    public function verifyPasswordResetToken(string $token): ?User
    {
        $record = PasswordResetToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if(!$record) {
            return null;
        }

        return User::where('email', $record->email)->first();
    }

}
