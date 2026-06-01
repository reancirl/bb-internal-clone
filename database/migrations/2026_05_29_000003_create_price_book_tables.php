<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique(); // "01".."19"
            $table->string('name');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('price_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit')->nullable(); // SF, LF, YD3, EA, HR, SET…
            $table->decimal('fast_price', 12, 2)->nullable();
            $table->decimal('material_cost', 12, 2)->nullable();
            $table->decimal('bb_install_rate', 12, 2)->nullable();
            $table->decimal('sub_install_rate', 12, 2)->nullable();
            $table->foreignId('preferred_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('price_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_items');
        Schema::dropIfExists('price_categories');
    }
};
