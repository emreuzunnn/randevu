<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('studios', function (Blueprint $table) {
            $table->foreignId('shop_id')
                ->nullable()
                ->after('owner_user_id')
                ->constrained('shops')
                ->nullOnDelete();
        });

        $owners = DB::table('studios')
            ->select('owner_user_id')
            ->whereNotNull('owner_user_id')
            ->distinct()
            ->pluck('owner_user_id');

        foreach ($owners as $ownerUserId) {
            $owner = DB::table('users')->where('id', $ownerUserId)->first();
            $shopId = DB::table('shops')->insertGetId([
                'manager_user_id' => $ownerUserId,
                'name' => trim(($owner->name ?? 'Varsayilan').' Dukkan'),
                'location' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('studios')
                ->where('owner_user_id', $ownerUserId)
                ->whereNull('shop_id')
                ->update([
                    'shop_id' => $shopId,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('studios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });

        Schema::dropIfExists('shops');
    }
};
