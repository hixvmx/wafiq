<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Internal discussion on a document; never shown to the client.
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->json('mentions')->nullable(); // user ids mentioned with @
            $table->timestamps();
        });

        // What happened to a document: team actions and client events, in one timeline.
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = the client or the system
            $table->string('type', 30); // App\Enums\ActivityType
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('comments');
    }
};
