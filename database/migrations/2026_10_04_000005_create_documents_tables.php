<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quotations and invoices share one table: same workflow, tracking, sharing and PDF code.
     * Amounts are integers in minor units of the document's currency.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // quote | invoice
            $table->string('number', 40);
            $table->unsignedSmallInteger('revision')->default(1);
            $table->unsignedBigInteger('root_id')->nullable()->index(); // first revision (v1) of this document
            $table->foreignId('parent_id')->nullable()->constrained('documents')->nullOnDelete(); // previous revision
            $table->boolean('is_latest')->default(true);
            $table->string('status', 10)->default('draft'); // App\Enums\DocumentStatus

            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->json('client_snapshot')->nullable(); // copied when sent
            $table->json('company_snapshot')->nullable(); // copied when sent

            $table->char('currency', 3);
            $table->date('issue_date');
            $table->date('valid_until')->nullable(); // quotes
            $table->date('due_date')->nullable(); // invoices

            $table->string('discount_type', 10)->default('percent'); // percent | amount
            $table->decimal('discount_value', 18, 3)->default(0); // percent, or amount in major units
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('documents')->nullOnDelete(); // invoice made from this quote

            // Client tracking (Phase 5).
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_ip', 45)->nullable();
            $table->text('approved_user_agent')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'type', 'number', 'revision']);
            $table->index(['company_id', 'type', 'is_latest', 'status']);
        });

        Schema::create('document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('qty', 12, 3);
            $table->string('unit', 30)->nullable();
            $table->bigInteger('unit_price_minor');
            $table->decimal('discount_percent', 6, 3)->default(0);
            // A copy of the tax rate at save time: editing a rate never changes a document.
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tax_name', 100)->nullable();
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->bigInteger('gross_minor');
            $table->bigInteger('discount_minor');
            $table->bigInteger('net_minor'); // shown as the line amount
            $table->bigInteger('discount_share_minor'); // share of the document discount
            $table->bigInteger('tax_minor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_lines');
        Schema::dropIfExists('documents');
    }
};
