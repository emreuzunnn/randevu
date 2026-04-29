<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('surname')->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('email');
        });

        Schema::table('studios', function (Blueprint $table) {
            $table->string('location')->nullable()->after('name');
        });

        Schema::table('studio_user', function (Blueprint $table) {
            $table->string('work_status')->default('working')->after('role');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('appointment_type')->default('standard')->after('assigned_driver_user_id');
            $table->string('place')->nullable()->after('room_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['appointment_type', 'place']);
        });

        Schema::table('studio_user', function (Blueprint $table) {
            $table->dropColumn('work_status');
        });

        Schema::table('studios', function (Blueprint $table) {
            $table->dropColumn('location');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['surname', 'phone']);
        });
    }
};
