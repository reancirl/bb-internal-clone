<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number'); // per-project: task #7 means the same thing to everyone
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable(); // "master bath"
            $table->string('category', 100)->nullable(); // paint | doors | electrical | ...
            $table->string('priority', 20)->default('medium');
            $table->boolean('is_punch')->default(false); // belongs to the closeout punch list
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open'); // open | in_progress | done
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('done')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('task_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 10); // before | after
            $table->string('path');
            $table->string('thumb_path');
            $table->string('original_name');
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            // Customer sign-off on the cleared punch list, recorded by office
            // staff — internal-only, same convention as Selections approvals.
            $table->timestamp('punch_signed_off_at')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('punch_signed_off_at');
        });
        Schema::dropIfExists('task_photos');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('project_tasks');
    }
};
