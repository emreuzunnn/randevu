<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixArtistOneStudioMembershipSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $artistOne = User::query()
                ->where('email', 'artist1@example.com')
                ->first();
            $piercingArtist = User::query()
                ->where('email', 'artist1b@example.com')
                ->first();
            $piercingStudio = Studio::query()
                ->where('slug', 'bagimsiz-piercing-studio')
                ->first();

            if ($artistOne === null || $piercingStudio === null) {
                return;
            }

            $piercingStudio->users()->updateExistingPivot($artistOne->id, [
                'is_active' => false,
                'left_at' => now(),
                'updated_at' => now(),
            ]);

            if ($piercingArtist === null) {
                return;
            }

            $existingMembership = $piercingStudio->users()
                ->where('users.id', $piercingArtist->id)
                ->first();

            if ($existingMembership === null) {
                $piercingStudio->users()->attach($piercingArtist->id, [
                    'role' => UserRole::Artist->value,
                    'work_status' => 'working',
                    'commission_rate' => 40,
                    'is_active' => true,
                    'joined_at' => now(),
                    'left_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $piercingStudio->users()->updateExistingPivot($piercingArtist->id, [
                    'role' => UserRole::Artist->value,
                    'work_status' => 'working',
                    'commission_rate' => $existingMembership->pivot->commission_rate ?: 40,
                    'is_active' => true,
                    'left_at' => null,
                    'updated_at' => now(),
                ]);
            }

            Appointment::query()
                ->where('studio_id', $piercingStudio->id)
                ->where('assigned_artist_user_id', $artistOne->id)
                ->update(['assigned_artist_user_id' => $piercingArtist->id]);
        });
    }
}
