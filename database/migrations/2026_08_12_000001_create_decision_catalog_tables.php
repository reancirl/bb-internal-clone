<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope')->default('shared')->index(); // living / garage / shared
            $table->unique(['name', 'scope']); // FLOORING exists in both living and garage scopes
            $table->smallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('decision_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_category_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('recommended')->nullable();
            $table->string('guidance')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['decision_category_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_items');
        Schema::dropIfExists('decision_categories');
    }
};
