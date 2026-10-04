<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One tracked link per send (channel + recipient), so the timeline can say
        // "opened via WhatsApp by Ahmed — 3 times". Only the token's hash is stored.
        Schema::create('document_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10); // email | whatsapp | link
            $table->string('recipient')->nullable(); // email address or phone
            $table->char('token_hash', 64)->unique();
            $table->text('message')->nullable(); // what was sent (WhatsApp text / email body)
            $table->string('email_status', 10)->nullable(); // sent | failed
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_send_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device', 10)->nullable(); // mobile | desktop
            $table->timestamps();

            $table->index(['document_send_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_views');
        Schema::dropIfExists('document_sends');
    }
};
