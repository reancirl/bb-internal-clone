<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('budget_line_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_section_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['budget_section_id', 'name']);
        });

        Schema::create('project_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_section_id')->constrained()->cascadeOnDelete();
            // Null for ad-hoc lines (e.g. change orders added per project).
            $table->foreignId('budget_line_definition_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name'); // copied from the definition, or custom
            $table->text('notes')->nullable();
            $table->bigInteger('bid_sub_cents')->nullable();
            $table->bigInteger('actual_sub_cents')->nullable();
            $table->bigInteger('estimated_material_cents')->nullable();
            $table->bigInteger('actual_material_cents')->nullable();
            $table->bigInteger('estimated_labor_cents')->nullable();
            $table->bigInteger('actual_labor_cents')->nullable();
            $table->timestamps();

            // One line per catalog definition per project (nulls exempt in PG).
            $table->unique(['project_id', 'budget_line_definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budget_lines');
        Schema::dropIfExists('budget_line_definitions');
        Schema::dropIfExists('budget_sections');
    }
};
