<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->userWithRole(RoleSlug::Administrator);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->userWithRole(RoleSlug::Administrator);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_users_cannot_delete_historical_accounts_from_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete('/profile');

        $response->assertMethodNotAllowed();
        $this->assertNotNull($user->fresh());
    }

    public function test_cashier_sees_profile_identity_as_read_only_and_cannot_update_it_from_backend(): void
    {
        $cashier = $this->userWithRole(RoleSlug::Cashier);
        $originalName = $cashier->name;
        $originalEmail = $cashier->email;

        $this->actingAs($cashier)->get('/profile')
            ->assertOk()
            ->assertSee('solo pueden ser modificados desde Administración')
            ->assertSee('id="name"', false)
            ->assertSee('id="email"', false)
            ->assertSee('disabled', false)
            ->assertDontSee('Guardar cambios');

        $this->actingAs($cashier)->patch('/profile', [
            'name' => 'Nombre manipulado',
            'email' => 'manipulado@example.com',
        ])->assertForbidden();

        $cashier->refresh();
        $this->assertSame($originalName, $cashier->name);
        $this->assertSame($originalEmail, $cashier->email);
    }

    private function userWithRole(RoleSlug $role): User
    {
        return User::factory()->create([
            'role_id' => Role::factory()->create([
                'name' => $role === RoleSlug::Administrator ? 'Administrador' : 'Cajero',
                'slug' => $role->value,
            ]),
        ]);
    }
}
