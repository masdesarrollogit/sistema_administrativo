<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tutores', function (Blueprint $table) {
            $table->unsignedBigInteger('moodle_user_id')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('tutores', function (Blueprint $table) {
            $table->dropColumn('moodle_user_id');
        });
    }
};
