<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_name')->nullable();
            $table->string('address')->nullable();
            $table->string('status')->default('lead'); // lead | active | complete

            // Takeoff dimension inputs (page 20–23 of the Material Master List).
            $table->decimal('house_sqft', 12, 2)->default(0);
            $table->decimal('garage_sqft', 12, 2)->default(0);
            $table->decimal('roof_sqft', 12, 2)->default(0);
            $table->decimal('valley_lft', 12, 2)->default(0);
            $table->decimal('eve_lft', 12, 2)->default(0);
            $table->decimal('rake_lft', 12, 2)->default(0);
            $table->decimal('ext_wall_lft', 12, 2)->default(0);
            $table->decimal('int_wall_lft', 12, 2)->default(0);
            $table->decimal('ext_wall_sqft', 12, 2)->default(0);
            $table->decimal('int_wall_sqft', 12, 2)->default(0);
            $table->decimal('ceiling_sqft', 12, 2)->default(0);
            $table->decimal('wall_height', 12, 2)->default(0);
            $table->decimal('footer_height', 12, 2)->default(0);
            $table->decimal('footer_width', 12, 2)->default(0);
            $table->decimal('slab_depth', 12, 2)->default(0);

            $table->timestamps();
        });

        Schema::create('takeoff_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('category')->nullable(); // CONCRETE, FRAMING, ROOFING…
            $table->string('item');
            $table->string('formula')->nullable();   // e.g. "ext_wall_lft * 2 / 16"
            $table->string('unit')->nullable();
            $table->decimal('waste_pct', 5, 2)->default(0);
            $table->boolean('ordered')->default(false);
            $table->boolean('on_site')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('takeoff_lines');
        Schema::dropIfExists('projects');
    }
};
