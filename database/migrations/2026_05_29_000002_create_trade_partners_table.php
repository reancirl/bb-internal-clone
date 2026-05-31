<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('used_before')->default(false);
            $table->string('negotiated_price')->nullable();
            $table->string('referral_source')->nullable(); // "How do we know them"
            $table->text('notes')->nullable();
            $table->boolean('do_not_use')->default(false);
            $table->timestamps();

            $table->index('location');
            $table->index('used_before');
        });

        Schema::create('trade_partner_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_partner_id')->constrained()->cascadeOnDelete();
            $table->string('trade');
            $table->timestamps();

            $table->index('trade');
            $table->unique(['trade_partner_id', 'trade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_partner_trades');
        Schema::dropIfExists('trade_partners');
    }
};
