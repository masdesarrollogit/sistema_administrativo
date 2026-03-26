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
        Schema::table('grupos_formativos', function (Blueprint $table) {
            $table->unsignedBigInteger('moodle_group_id')->nullable()->after('id_grupo_fundae');
            $table->unsignedBigInteger('moodle_course_id')->nullable()->after('moodle_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('grupos_formativos', function (Blueprint $table) {
            $table->dropColumn(['moodle_group_id', 'moodle_course_id']);
        });
    }
};
