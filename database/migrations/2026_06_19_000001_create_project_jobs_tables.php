<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->date('scheduled_date');
            $table->string('status')->default('scheduled'); // scheduled | in_progress | done | canceled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('scheduled_date');
            $table->index(['project_id', 'scheduled_date']);
        });

        Schema::create('project_job_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_job_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_job_user');
        Schema::dropIfExists('project_jobs');
    }
};
