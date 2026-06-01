<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labor_rates', function (Blueprint $table) {
            $table->id();
            $table->string('class_name');
            $table->decimal('base_rate', 10, 2)->nullable();
            $table->decimal('burden_rate', 10, 2)->nullable(); // base w/ burden
            $table->decimal('bill_rate', 10, 2)->nullable();   // min bill rate
            $table->decimal('total', 10, 2)->nullable();        // our cost total
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('day_rate', 10, 2)->nullable();
            $table->decimal('week_rate', 10, 2)->nullable();
            $table->decimal('month_rate', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_rates');
        Schema::dropIfExists('labor_rates');
    }
};
