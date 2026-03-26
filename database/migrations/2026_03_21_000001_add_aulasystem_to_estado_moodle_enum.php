<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Expandir enum para incluir 'aulasystem'
        DB::statement("ALTER TABLE grupo_formativo_alumno MODIFY COLUMN estado_moodle ENUM('pendiente','creado','matriculado','error','aulasystem') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        // Primero revertir los valores existentes antes de reducir el enum
        DB::statement("UPDATE grupo_formativo_alumno SET estado_moodle = 'matriculado' WHERE estado_moodle = 'aulasystem'");
        DB::statement("ALTER TABLE grupo_formativo_alumno MODIFY COLUMN estado_moodle ENUM('pendiente','creado','matriculado','error') NOT NULL DEFAULT 'pendiente'");
    }
};
