<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bid_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title'); // "Framing — full scope", "Excavation & grading"
            // Same vocabulary as trade_partner_trades.trade — powers the partner-picker filter.
            $table->string('trade')->nullable();
            $table->text('scope_description')->nullable(); // what the subs are pricing; locked after draft
            $table->date('due_date')->nullable();
            $table->string('status')->default('draft'); // draft | open | awarded | canceled
            // Cost-tracking line created in the budget's SUB BIDS section on award.
            $table->foreignId('budget_line_id')->nullable()->constrained('project_budget_lines')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('bid_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_request_id')->constrained()->cascadeOnDelete();
            // Partner may be deleted from the directory later; the response keeps
            // a name snapshot so the quote history survives (same as PO vendors).
            $table->foreignId('trade_partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trade_partner_name');
            $table->string('status')->default('invited'); // invited | received | declined
            $table->bigInteger('amount_cents')->nullable(); // null until a quote is recorded
            $table->text('notes')->nullable(); // "includes materials", exclusions, lead time
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['bid_request_id', 'trade_partner_id']);
        });

        // The winner FK points back into bid_responses, so it can only be added
        // once both tables exist.
        Schema::table('bid_requests', function (Blueprint $table) {
            $table->foreignId('awarded_response_id')->nullable()->constrained('bid_responses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bid_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('awarded_response_id');
        });
        Schema::dropIfExists('bid_responses');
        Schema::dropIfExists('bid_requests');
    }
};
