<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Soft deletes: documents keep a snapshot of the client anyway, but history stays linkable.
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('company'); // company | person
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable(); // international format, used for WhatsApp
            $table->string('vat_number', 50)->nullable();
            $table->string('cr_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'name']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('service'); // product | service
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 30)->nullable();
            $table->bigInteger('price_minor')->default(0);
            $table->char('currency', 3);
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete(); // null = the default rate
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
        Schema::dropIfExists('clients');
    }
};
