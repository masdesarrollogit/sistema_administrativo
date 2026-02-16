<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Spatie\Permission\Models\Role::create(['name' => 'SuperAdmin']);
        \Spatie\Permission\Models\Role::create(['name' => 'Gestor']);
        \Spatie\Permission\Models\Role::create(['name' => 'Usuario']);
    }
}
