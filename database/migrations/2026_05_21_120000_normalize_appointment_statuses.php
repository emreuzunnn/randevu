<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('appointments')
            ->where('status', 'pending')
            ->update(['status' => 'confirmed']);

        DB::table('appointments')
            ->where('artist_status', 'pending')
            ->update(['artist_status' => null]);
    }

    public function down(): void
    {
        //
    }
};
