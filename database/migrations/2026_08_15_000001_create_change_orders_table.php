<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Original contracted amount ("OG Contract" in the Marys REVAMP sheet).
            $table->bigInteger('contract_price_cents')->nullable();
        });

        Schema::create('change_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number'); // CO-1, CO-2, … per project
            $table->string('title');
            $table->text('description')->nullable(); // agreed scope
            $table->bigInteger('price_cents')->nullable(); // price to the customer
            $table->string('status')->default('pending'); // pending / approved / declined
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_comment')->nullable();
            // Cost-tracking line created in the budget's CHANGE ORDERS section on approval.
            $table->foreignId('budget_line_id')->nullable()->constrained('project_budget_lines')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_orders');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('contract_price_cents');
        });
    }
};
