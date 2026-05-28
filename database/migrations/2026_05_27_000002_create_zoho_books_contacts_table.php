<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoho_books_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('contact_id', 64)->unique();
            $table->string('organization_id', 64)->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('cif', 20)->nullable()->index();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('contact_type', 32)->nullable();
            $table->string('status', 32)->nullable();
            $table->decimal('outstanding_receivable_amount', 15, 2)->default(0);
            $table->string('currency_code', 8)->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_books_contacts');
    }
};
