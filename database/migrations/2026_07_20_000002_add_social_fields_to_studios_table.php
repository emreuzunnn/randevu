<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studios', function (Blueprint $table): void {
            $table->string('instagram')->nullable()->after('logo_path');
            $table->string('facebook')->nullable()->after('instagram');
        });
    }

    public function down(): void
    {
        Schema::table('studios', function (Blueprint $table): void {
            $table->dropColumn(['instagram', 'facebook']);
        });
    }
};
