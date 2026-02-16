<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // En MySQL, para modificar un ENUM lo más seguro es usar un ALTER TABLE directo
        DB::statement("ALTER TABLE candidatos MODIFY COLUMN estatus ENUM('pendiente', 'completo', 'cancelado', 'pausado', 'desactivado') DEFAULT 'pendiente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volvemos al estado anterior (asegurarse de que no haya registros con 'desactivado' antes de revertir)
        DB::statement("UPDATE candidatos SET estatus = 'cancelado' WHERE estatus = 'desactivado'");
        DB::statement("ALTER TABLE candidatos MODIFY COLUMN estatus ENUM('pendiente', 'completo', 'cancelado', 'pausado') DEFAULT 'pendiente'");
    }
};
