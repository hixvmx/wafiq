<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Document lines copy the name and rate when they're saved, so editing or
        // deleting a rate never changes an existing document.
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('rate', 6, 3); // percent, e.g. 15.000
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // The next number per document type (and per year when numbers restart every year).
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // quote | invoice
            $table->unsignedSmallInteger('year')->default(0); // 0 = numbering never restarts
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('tax_rates');
    }
};
