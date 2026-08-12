<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decision_item_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('allowance_cents')->nullable();
            $table->date('deadline_date')->nullable();
            $table->text('notes')->nullable();
            // Approval — mirrors Buildertrend: one approved choice per selection,
            // recorded by office staff on behalf of the customer.
            $table->foreignId('approved_choice_id')->nullable(); // FK added below, table order
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_comment')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'decision_item_id']);
        });

        Schema::create('selection_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_selection_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->bigInteger('price_cents')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('project_selections', function (Blueprint $table) {
            $table->foreign('approved_choice_id')
                ->references('id')->on('selection_choices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_selections', function (Blueprint $table) {
            $table->dropForeign(['approved_choice_id']);
        });
        Schema::dropIfExists('selection_choices');
        Schema::dropIfExists('project_selections');
    }
};
