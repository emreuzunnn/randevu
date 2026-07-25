<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::table('studios', function (Blueprint $table): void {
            $table->boolean('discovery_visible')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('studios', function (Blueprint $table): void {
            $table->dropColumn('discovery_visible');
        });

        Schema::dropIfExists('app_settings');
    }
};
