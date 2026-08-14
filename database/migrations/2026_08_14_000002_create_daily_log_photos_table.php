<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_log_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->string('original_name');
            $table->unsignedBigInteger('size_bytes');
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_log_photos');
    }
};
