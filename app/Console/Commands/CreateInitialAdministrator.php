<?php

namespace App\Console\Commands;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateInitialAdministrator extends Command
{
    protected $signature = 'micromarket:create-admin';

    protected $description = 'Crea de forma interactiva el administrador inicial';

    public function handle(): int
    {
        $role = Role::query()->where('slug', RoleSlug::Administrator->value)->first();
        $branch = Branch::query()->where('code', 'PRINCIPAL')->where('is_active', true)->first();

        if (! $role || ! $branch) {
            $this->error('Primero ejecute las migraciones y semillas de la aplicación.');

            return self::FAILURE;
        }

        if (User::query()->where('role_id', $role->id)->exists()) {
            $this->error('El administrador inicial ya existe.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nombre completo'));
        $email = strtolower(trim((string) $this->ask('Correo electrónico')));
        $password = (string) $this->secret('Contraseña');
        $confirmation = (string) $this->secret('Confirmar contraseña');

        $validator = Validator::make(compact('name', 'email', 'password', 'confirmation'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults(), 'same:confirmation'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $role->id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->info('Administrador inicial creado correctamente.');

        return self::SUCCESS;
    }
}
