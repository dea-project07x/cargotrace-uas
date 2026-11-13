<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFixtures;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // views reference compiled assets that do not exist in the test run
    }

    public function test_login_page_is_displayed(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/shipments')->assertRedirect('/login');
    }

    public function test_user_can_sign_in_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'ops@example.com', 'role' => UserRole::Operator]);

        $this->post('/login', ['email' => 'ops@example.com', 'password' => 'password'])
            ->assertRedirect(route('shipments.index'));

        $this->assertAuthenticated();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'ops@example.com', 'role' => UserRole::Operator]);

        $this->post('/login', ['email' => 'ops@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_sign_out(): void
    {
        $user = $this->userWithRole(UserRole::Customer);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
