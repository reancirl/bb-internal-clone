<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // E.164, e.g. +639171234567. Normalised by ProfileUpdateRequest.
            $table->string('phone', 20)->nullable()->after('email');
            // Off by default: texts cost money and reach a personal phone, so
            // nobody gets one without switching it on themselves.
            $table->boolean('sms_notifications')->default(false)->after('email_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'sms_notifications']);
        });
    }
};
