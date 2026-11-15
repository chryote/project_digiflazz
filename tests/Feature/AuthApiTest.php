<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Merchant Test',
            'email' => 'merchant@example.com',
            'username' => 'merchant_user',
            'password' => Hash::make('password123'),
            'api_key' => 'api_key_test_123',
            'balance' => 100000,
        ]);
    }

    public function test_user_can_login_with_valid_username_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'merchant_user',
            'password' => 'password123',
            'device_name' => 'PHPUnit Test',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'username', 'api_key', 'balance'],
                    'token' => ['access_token', 'token_type', 'expires_at'],
                ],
            ]);
    }

    public function test_user_can_login_with_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'merchant@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'merchant_user',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_login_validation_requires_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_access_me_profile(): void
    {
        $token = $this->user->createToken('Test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.username', 'merchant_user');
    }

    public function test_unauthenticated_request_to_me_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout_and_revoke_token(): void
    {
        $token = $this->user->createToken('Test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify token record is removed from personal_access_tokens
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Reset auth guard state for subsequent request
        $this->app['auth']->forgetGuards();

        $nextResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $nextResponse->assertStatus(401);
    }

    public function test_authenticated_user_can_regenerate_api_key(): void
    {
        $oldKey = $this->user->api_key;
        $token = $this->user->createToken('Test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/regenerate-api-key');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEquals($oldKey, $this->user->fresh()->api_key);
    }
}
