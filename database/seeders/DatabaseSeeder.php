<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Role::query()->updateOrCreate(
            ['slug' => RoleSlug::Administrator->value],
            ['name' => 'Administrador'],
        );

        Role::query()->updateOrCreate(
            ['slug' => RoleSlug::Cashier->value],
            ['name' => 'Cajero'],
        );

        Branch::query()->updateOrCreate(
            ['code' => 'PRINCIPAL'],
            ['name' => 'Sucursal principal', 'is_active' => true],
        );
    }
}
