<?php

namespace App\Services\Auth;

use App\DTOs\Auth\LoginDTO;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate user and issue personal access token.
     *
     * @throws ValidationException
     */
    public function login(LoginDTO $dto): array
    {
        $user = User::where('email', $dto->login)
            ->orWhere('username', $dto->login)
            ->first();

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Kredensial yang diberikan tidak cocok dengan data kami.'],
            ]);
        }

        // Create Sanctum personal access token
        $token = $user->createToken(
            name: $dto->deviceName ?? 'API Client',
            abilities: ['*'],
            expiresAt: now()->addDays(30)
        );

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'api_key' => $user->api_key,
                'balance' => (float) $user->balance,
                'webhook_url' => $user->webhook_url,
            ],
            'token' => [
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(30)->toIso8601String(),
            ],
        ];
    }

    /**
     * Revoke current access token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Get authenticated user profile.
     */
    public function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'api_key' => $user->api_key,
            'balance' => (float) $user->balance,
            'webhook_url' => $user->webhook_url,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Regenerate user API Key under database transaction lock.
     */
    public function regenerateApiKey(User $user): string
    {
        return DB::transaction(function () use ($user) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            $newApiKey = 'key_' . bin2hex(random_bytes(16));
            $lockedUser->update(['api_key' => $newApiKey]);
            $user->api_key = $newApiKey;

            return $newApiKey;
        });
    }
}
