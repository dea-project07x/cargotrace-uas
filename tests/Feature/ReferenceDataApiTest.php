<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFixtures;
use Tests\TestCase;

class ReferenceDataApiTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    public function test_admin_manages_carriers_and_locations(): void
    {
        $this->actingAsRole(UserRole::Admin);

        $this->postJson('/api/carriers', ['name' => 'Blue Sea', 'code' => 'BSE'])->assertCreated();
        $this->postJson('/api/carriers', ['name' => 'Blue Sea', 'code' => 'BSE'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'code']);

        $this->postJson('/api/locations', [
            'name' => 'Port X', 'code' => 'IDPRX', 'city' => 'X', 'country_code' => 'ID',
            'latitude' => 95, 'longitude' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors(['latitude']);

        $this->getJson('/api/carriers')->assertOk();
    }

    public function test_operator_can_read_but_not_create_and_customer_has_no_access(): void
    {
        $this->actingAsRole(UserRole::Operator);
        $this->getJson('/api/carriers')->assertOk();
        $this->postJson('/api/carriers', ['name' => 'N', 'code' => 'N1'])->assertForbidden();

        $this->actingAsRole(UserRole::Customer);
        $this->getJson('/api/carriers')->assertForbidden();
        $this->getJson('/api/locations')->assertForbidden();
    }
}
