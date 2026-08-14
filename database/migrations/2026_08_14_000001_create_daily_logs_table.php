<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // author
            $table->date('log_date');
            $table->text('notes'); // work completed / progress
            $table->string('weather')->nullable();
            $table->integer('temperature_f')->nullable();
            $table->string('crew_present')->nullable(); // free text, e.g. "Wyatt, Matt + concrete sub"
            $table->text('issues')->nullable(); // delays, problems, incidents
            $table->timestamps();

            $table->index(['project_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_logs');
    }
};
