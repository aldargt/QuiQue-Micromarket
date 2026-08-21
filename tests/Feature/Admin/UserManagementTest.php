<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Role $administratorRole;

    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'PRINCIPAL']);
        $this->administratorRole = Role::factory()->create([
            'name' => 'Administrador',
            'slug' => RoleSlug::Administrator->value,
        ]);
        $this->cashierRole = Role::factory()->create([
            'name' => 'Cajero',
            'slug' => RoleSlug::Cashier->value,
        ]);
    }

    public function test_administrator_can_access_user_management(): void
    {
        $this->actingAs($this->administrator())->get(route('admin.users.index'))->assertOk();
        $create = $this->get(route('admin.users.create'))->assertOk();
        $create->assertSee('autocomplete="email"', false)->assertSee('select-placeholder', false);
        $this->assertSame(5, substr_count($create->getContent(), '(obligatorio)'));
        $edit = $this->get(route('admin.users.edit', $this->cashier()))->assertOk();
        $this->assertSame(2, substr_count($edit->getContent(), '(obligatorio)'));
    }

    public function test_administrator_and_cashier_can_log_in(): void
    {
        foreach ([$this->administrator(), $this->cashier()] as $user) {
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route('dashboard', absolute: false));
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
    }

    public function test_cashier_cannot_access_administrative_routes_directly(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($cashier)->put(route('admin.users.update', $cashier), [])->assertForbidden();
    }

    public function test_administrator_can_create_cashier_without_privilege_escalation(): void
    {
        $response = $this->actingAs($this->administrator())->post(route('admin.users.store'), [
            'name' => 'Caja Uno',
            'email' => 'caja1@example.com',
            'branch_id' => $this->branch->id,
            'password' => 'Clave-Segura-123',
            'password_confirmation' => 'Clave-Segura-123',
            'role_id' => $this->administratorRole->id,
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $user = User::query()->where('email', 'caja1@example.com')->firstOrFail();

        $this->assertSame($this->cashierRole->id, $user->role_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Clave-Segura-123', $user->password));
        $this->assertNotSame('Clave-Segura-123', $user->password);
    }

    public function test_administrator_can_update_and_deactivate_cashier_but_not_role(): void
    {
        $cashier = $this->cashier();

        $response = $this->actingAs($this->administrator())->put(route('admin.users.update', $cashier), [
            'name' => 'Caja Actualizada',
            'email' => 'actualizada@example.com',
            'branch_id' => $this->branch->id,
            'is_active' => false,
            'role_id' => $this->administratorRole->id,
        ]);

        $response->assertRedirect(route('admin.users.index'))->assertSessionHas('status');
        $cashier->refresh();
        $this->assertSame('Caja Actualizada', $cashier->name);
        $this->assertFalse($cashier->is_active);
        $this->assertSame($this->cashierRole->id, $cashier->role_id);
    }

    public function test_administrator_can_reactivate_cashier_and_reset_hashed_password(): void
    {
        $cashier = $this->cashier(['is_active' => false]);

        $this->actingAs($this->administrator())->put(route('admin.users.update', $cashier), [
            'name' => $cashier->name,
            'email' => $cashier->email,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->administrator())->put(route('admin.users.password.update', $cashier), [
            'password' => 'Nueva-Clave-456',
            'password_confirmation' => 'Nueva-Clave-456',
        ])->assertSessionHasNoErrors();

        $cashier->refresh();
        $this->assertTrue($cashier->is_active);
        $this->assertTrue(Hash::check('Nueva-Clave-456', $cashier->password));
        $this->assertNotSame('Nueva-Clave-456', $cashier->password);
    }

    public function test_administrator_cannot_edit_another_administrator(): void
    {
        $target = $this->administrator();

        $this->actingAs($this->administrator())
            ->get(route('admin.users.edit', $target))
            ->assertForbidden();
    }

    private function administrator(): User
    {
        return User::factory()->create([
            'role_id' => $this->administratorRole->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function cashier(array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'branch_id' => $this->branch->id,
            ...$attributes,
        ]);
    }
}
