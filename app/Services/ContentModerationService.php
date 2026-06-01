<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;

class ContentModerationService
{
    public function ban(User $user, ?string $reason = null): void
    {
        $user->forceFill([
            'banned_at' => now(),
            'ban_reason' => $reason,
            'api_token' => null,
        ])->save();

        $this->refreshReviewedArtistRatings($user);
    }

    public function unban(User $user): void
    {
        $user->forceFill([
            'banned_at' => null,
            'ban_reason' => null,
        ])->save();

        $this->refreshReviewedArtistRatings($user);
    }

    private function refreshReviewedArtistRatings(User $reviewer): void
    {
        Review::query()
            ->where('user_id', $reviewer->id)
            ->whereNotNull('artist_id')
            ->pluck('artist_id')
            ->unique()
            ->each(function ($artistId): void {
                $artist = User::query()->find($artistId);
                if ($artist === null) {
                    return;
                }

                $average = Review::query()
                    ->where('artist_id', $artist->id)
                    ->whereHas('reviewer', fn ($query) => $query->whereNull('banned_at'))
                    ->avg('rating');

                $artist->forceFill([
                    'rating' => $average !== null ? round((float) $average, 1) : null,
                ])->save();
            });
    }
}
