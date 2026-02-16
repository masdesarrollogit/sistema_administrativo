<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class WebcursoRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear el rol admin si no existe
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web']
        );

        // Crear permisos para el módulo webcurso
        $permissions = [
            'webcurso.dashboard',
            'webcurso.empresas.view',
            'webcurso.empresas.edit',
            'webcurso.grupos.view',
            'webcurso.grupos.edit',
            'webcurso.import.csv',
            'webcurso.send.email',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        // Asignar todos los permisos al rol admin
        $adminRole->syncPermissions(Permission::all());

        $this->command->info('✅ Rol admin creado con permisos de webcurso');

        // Si hay usuarios y ninguno tiene el rol admin, asignar al primero
        $firstUser = User::first();
        if ($firstUser && !$firstUser->hasRole('admin')) {
            $firstUser->assignRole('admin');
            $this->command->info("✅ Rol admin asignado al usuario: {$firstUser->email}");
        }
    }
}
