<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $memberships = DB::table('studio_user')
            ->where('is_active', true)
            ->whereIn('role', ['supervisor', 'sofor'])
            ->orderBy('user_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('studio_id')
            ->get();

        foreach ($memberships->groupBy('user_id') as $userMemberships) {
            foreach ($userMemberships->skip(1) as $membership) {
                DB::table('studio_user')
                    ->where('studio_id', $membership->studio_id)
                    ->where('user_id', $membership->user_id)
                    ->update([
                        'is_active' => false,
                        'left_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Pasife alınan eski üyelikleri güvenli biçimde ayırt etmek mümkün değildir.
    }
};
