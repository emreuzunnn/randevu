<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('instagram', 100)->nullable()->after('specializations');
            $table->string('whatsapp', 30)->nullable()->after('instagram');
            $table->unsignedTinyInteger('response_time_hours')->nullable()->after('whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['instagram', 'whatsapp', 'response_time_hours']);
        });
    }
};
