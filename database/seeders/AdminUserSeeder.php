<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Required env variables:
     *   ADMIN_EMAIL=admin@example.com
     *   ADMIN_PASSWORD=your-secure-password
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (!$email || !$password) {
            $this->command->error('❌ Debes definir ADMIN_EMAIL y ADMIN_PASSWORD en tu archivo .env');
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador WebCurso',
                'username' => 'admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole($adminRole);

        $this->command->info('✅ Usuario admin creado/actualizado correctamente');
        $this->command->info('   Rol: admin');
    }
}
