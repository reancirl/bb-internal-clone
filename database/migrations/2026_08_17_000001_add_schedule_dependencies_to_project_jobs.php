<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->foreignId('predecessor_job_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_jobs')
                ->nullOnDelete();
            $table->unsignedSmallInteger('duration_days')->default(1)->after('scheduled_date');
            $table->string('trade')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('predecessor_job_id');
            $table->dropColumn(['duration_days', 'trade']);
        });
    }
};
