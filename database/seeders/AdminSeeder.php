<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Required env variables:
     *   SUPERADMIN_EMAIL=admin@example.com
     *   SUPERADMIN_PASSWORD=your-secure-password
     */
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL');
        $password = env('SUPERADMIN_PASSWORD');

        if (!$email || !$password) {
            $this->command->error('❌ Debes definir SUPERADMIN_EMAIL y SUPERADMIN_PASSWORD en tu archivo .env');
            return;
        }

        $user = \App\Models\User::create([
            'name' => 'Super Administrador',
            'email' => $email,
            'username' => 'admin',
            'password' => \Illuminate\Support\Facades\Hash::make($password),
        ]);

        $user->assignRole('SuperAdmin');

        $this->command->info('✅ SuperAdmin creado correctamente');
    }
}
