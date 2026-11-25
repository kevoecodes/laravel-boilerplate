<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'password', 'is_active' => true]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'password']);

        $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['email']]);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        User::factory()->create(['email' => 'off@example.com', 'password' => 'password', 'is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'off@example.com', 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_validates_input(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_unauthenticated_request_without_json_header_still_returns_json(): void
    {
        $this->get('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
