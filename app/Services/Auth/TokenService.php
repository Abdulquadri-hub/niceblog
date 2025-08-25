<?php

namespace App\Services\Auth;

use App\Contracts\Services\TokenServiceInterface;
use App\Models\User;
use App\Repositories\TokenRepository;
use Illuminate\Http\Request;

class TokenService implements TokenServiceInterface
{
    protected $tokenRepo;

    public function __construct(TokenRepository $tokenRepo) {
        $this->tokenRepo = $tokenRepo;
    }

    public function generateAuthToken(User $user): ?string
    {
        return $user->createToken('auth-token')->plainTextToken;
    }

    public function generateEmailVerificationToken(User $user): ?string
    {
        $token = rand(000000, 999999);

        return $this->tokenRepo->createEmailVerificationToken($user, $token);
    }

    public function generatePasswordResetToken(User $user): ?string
    {
        $token = rand(000000, 999999);

        return $this->tokenRepo->createPasswordResetToken($user, $token);
    }

    public function verifyEmailToken(string $token): ?User
    {
      return $this->tokenRepo->verifyEmailToken($token);
    }

    public function verifyPasswordResetToken(string $token): ?User
    {
        return $this->tokenRepo->verifyPasswordResetToken($token);
    }

    public function revokeCurrentToken(Request $request): void
    {
        $request->user()->currentAccessToken()->delete();
    }

    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }
}
