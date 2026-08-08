<?php

namespace Tests\Feature\Console;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateInitialAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_initial_administrator_securely(): void
    {
        $role = Role::factory()->create([
            'name' => 'Administrador',
            'slug' => RoleSlug::Administrator->value,
        ]);
        $branch = Branch::factory()->create(['code' => 'PRINCIPAL', 'is_active' => true]);

        $this->artisan('micromarket:create-admin')
            ->expectsQuestion('Nombre completo', 'Administrador Principal')
            ->expectsQuestion('Correo electrónico', 'admin@example.com')
            ->expectsQuestion('Contraseña', 'Clave-Segura-123')
            ->expectsQuestion('Confirmar contraseña', 'Clave-Segura-123')
            ->expectsOutput('Administrador inicial creado correctamente.')
            ->assertSuccessful();

        $administrator = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame($role->id, $administrator->role_id);
        $this->assertSame($branch->id, $administrator->branch_id);
        $this->assertTrue($administrator->is_active);
        $this->assertTrue(Hash::check('Clave-Segura-123', $administrator->password));
        $this->assertNotSame('Clave-Segura-123', $administrator->password);
    }

    public function test_command_refuses_to_create_a_second_administrator(): void
    {
        $role = Role::factory()->create(['slug' => RoleSlug::Administrator->value]);
        Branch::factory()->create(['code' => 'PRINCIPAL', 'is_active' => true]);
        User::factory()->create(['role_id' => $role->id]);

        $this->artisan('micromarket:create-admin')
            ->expectsOutput('El administrador inicial ya existe.')
            ->assertFailed();
    }
}
