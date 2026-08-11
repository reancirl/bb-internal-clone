<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('status')->default('new')->index();
            $table->string('priority')->default('medium');
            $table->bigInteger('estimated_value_cents')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('lost_reason')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->foreignId('converted_project_id')->nullable()->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to_user_id');
            $table->dropConstrainedForeignId('converted_project_id');
            $table->dropColumn([
                'status', 'priority', 'estimated_value_cents', 'next_follow_up_date',
                'lost_reason', 'won_at', 'lost_at',
            ]);
        });
    }
};
