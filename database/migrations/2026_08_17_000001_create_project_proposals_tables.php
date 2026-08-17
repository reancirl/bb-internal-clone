<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique(); // "PROP-2026-001"
            $table->string('title')->default('Construction Proposal');
            $table->string('status')->default('draft'); // draft | sent | accepted | rejected
            $table->bigInteger('total_cents')->default(0);
            $table->text('payment_terms')->nullable();
            $table->text('notes')->nullable(); // customer-facing remarks on the PDF
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('project_proposal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_proposal_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('item');
            $table->decimal('qty', 12, 2)->nullable();
            $table->string('unit')->nullable();
            $table->bigInteger('unit_price_cents')->nullable();
            $table->bigInteger('total_cents')->nullable(); // null = unpriced line
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['project_proposal_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_proposal_lines');
        Schema::dropIfExists('project_proposals');
    }
};
