<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('zoho_contact_id', 64)->nullable()->after('email');
            $table->index('zoho_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropIndex(['zoho_contact_id']);
            $table->dropColumn('zoho_contact_id');
        });
    }
};
