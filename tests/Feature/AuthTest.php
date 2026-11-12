<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']); // factory password: "password"

        $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email', 'role'], 'token']);
    }

    public function test_login_with_invalid_credentials_is_rejected(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized();
    }

    public function test_login_validates_input(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_protected_endpoints_require_authentication(): void
    {
        $this->getJson('/api/shipments')->assertUnauthorized();
        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/shipments', [])->assertUnauthorized();
    }

    public function test_register_always_creates_a_customer_even_if_role_is_sent(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Eve',
            'email' => 'eve@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => 'admin',
        ])->assertCreated();

        $response->assertJsonPath('user.role', 'customer');
        $this->assertDatabaseHas('users', ['email' => 'eve@example.com', 'role' => 'customer']);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        $token = $this->postJson('/api/login', ['email' => 'a@example.com', 'password' => 'password'])
            ->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
