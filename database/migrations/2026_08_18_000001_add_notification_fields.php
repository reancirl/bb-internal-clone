<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_notifications')->default(true)->after('role');
        });

        Schema::table('time_cards', function (Blueprint $table) {
            // Stamped when the forgot-to-clock-out nudge is sent, so a long
            // shift is reminded once, not every hour.
            $table->timestamp('reminder_sent_at')->nullable()->after('clock_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_notifications');
        });

        Schema::table('time_cards', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
