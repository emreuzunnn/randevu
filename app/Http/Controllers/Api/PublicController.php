<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    /** Stüdyo herkese açık detay + portfolio + istatistikler */
    public function studio(Studio $studio): JsonResponse
    {
        $studio->load(['shop.company']);

        $artists = $studio->users()
            ->wherePivotIn('role', [UserRole::Artist->value, UserRole::Designer->value])
            ->wherePivot('is_active', true)
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.surname',
                'users.username',
                'users.profile_image',
                'users.bio',
                'users.location',
                'users.experience_years',
                'users.specializations',
                'users.availability_start',
                'users.availability_end',
                'users.rating',
                'users.portfolio',
            ]);

        $staff = $artists->map(fn ($a): array => [
            'id'                 => $a->id,
            'name'               => $a->fullName(),
            'username'           => $a->username,
            'role'               => $a->pivot->role,
            'role_label'         => UserRole::fromValue($a->pivot->role)->label(),
            'profile_image'      => $a->profile_image,
            'bio'                => $a->bio,
            'location'           => $a->location,
            'experience_years'   => $a->experience_years,
            'specializations'    => $a->specializations ?? [],
            'availability_start' => $a->availability_start,
            'availability_end'   => $a->availability_end,
            'rating'             => $a->rating,
            'portfolio'          => $a->portfolio ?? [],
        ])->values();

        $stats = Appointment::query()
            ->where('studio_id', $studio->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $reviewStats = Review::query()
            ->where('studio_id', $studio->id)
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')
            ->first();

        $studioPortfolio = $this->galleryItems($studio->gallery_images ?? []);
        $shopPortfolio = $this->galleryItems($studio->shop?->gallery_images ?? []);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'             => $studio->id,
                'name'           => $studio->name,
                'slug'           => $studio->slug,
                'location'       => $studio->location,
                'about'          => $studio->about,
                'logo_path'      => $studio->logo_path,
                'rating'         => $reviewStats?->avg_rating !== null ? round((float) $reviewStats->avg_rating, 1) : null,
                'review_count'   => (int) ($reviewStats->total ?? 0),
                'opening_time'   => $studio->opening_time ?? $studio->shop?->opening_time,
                'closing_time'   => $studio->closing_time ?? $studio->shop?->closing_time,
                'gallery_images' => $studioPortfolio,
                'portfolio'      => $studioPortfolio,
                'aggregated_gallery' => array_values(array_unique(array_merge($studioPortfolio, $shopPortfolio))),
                'shop'           => $studio->shop ? [
                    'id'           => $studio->shop->id,
                    'name'         => $studio->shop->name,
                    'location'     => $studio->shop->location,
                    'logo_path'    => $studio->shop->logo_path,
                    'opening_time' => $studio->shop->opening_time,
                    'closing_time' => $studio->shop->closing_time,
                    'gallery_images' => $shopPortfolio,
                    'company'      => $studio->shop->company ? [
                        'id'        => $studio->shop->company->id,
                        'name'      => $studio->shop->company->name,
                        'logo_path' => $studio->shop->company->logo_path,
                        'about'     => $studio->shop->company->about,
                        'website'   => $studio->shop->company->website,
                    ] : null,
                ] : null,
                'appointment_stats' => [
                    'total'     => $stats->sum(),
                    'completed' => (int) ($stats['completed'] ?? 0),
                    'accepted'  => (int) ($stats['confirmed'] ?? 0),
                    'cancelled' => (int) ($stats['cancelled'] ?? 0),
                    'pending'   => (int) ($stats['pending'] ?? 0),
                ],
                'staff'     => $staff,
                'artists'   => $staff->where('role', UserRole::Artist->value)->values(),
                'designers' => $staff->where('role', UserRole::Designer->value)->values(),
            ],
        ]);
    }

    /** Artist / rol sahibi kullanıcı herkese açık profil */
    public function artist(User $user): JsonResponse
    {
        abort_unless(
            $user->hasAnyRole([UserRole::Artist, UserRole::Designer, UserRole::KullaniciRol]),
            404
        );

        $studioMemberships = $user->studios()
            ->wherePivot('is_active', true)
            ->get(['studios.id', 'studios.name', 'studios.location', 'studios.logo_path']);

        // Portfolyodaki benzersiz kategorileri uzmanlık alanı olarak döndür
        $portfolio  = $user->portfolio ?? [];
        $specialties = collect($portfolio)
            ->pluck('category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $reviewStats = Review::query()
            ->where('artist_id', $user->id)
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')
            ->first();

        $appointmentStats = Appointment::query()
            ->where('assigned_artist_user_id', $user->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'data' => [
                'id'                  => $user->id,
                'name'                => $user->fullName(),
                'username'            => $user->username,
                'role'                => $user->role?->value,
                'role_label'          => $user->role?->label(),
                'profile_image'       => $user->profile_image,
                'bio'                 => $user->bio,
                'location'            => $user->location,
                'experience_years'    => $user->experience_years,
                'specializations'     => $user->specializations ?? [],
                'specialties'         => $specialties,
                'rating'              => $user->rating,
                'review_count'        => (int) ($reviewStats->total ?? 0),
                'response_time_hours' => $user->response_time_hours,
                'appointment_stats'   => [
                    'completed' => (int) ($appointmentStats['completed'] ?? 0),
                    'cancelled' => (int) ($appointmentStats['cancelled'] ?? 0),
                    'total'     => $appointmentStats->sum(),
                ],
                'portfolio'           => $portfolio,
                'social'              => [
                    'instagram' => $user->instagram,
                    'whatsapp'  => $user->whatsapp,
                ],
                'availability_start' => $user->availability_start,
                'availability_end'   => $user->availability_end,
                'studios'            => $studioMemberships->map(fn ($s): array => [
                    'id'        => $s->id,
                    'name'      => $s->name,
                    'location'  => $s->location,
                    'logo_path' => $s->logo_path,
                ])->values(),
            ],
        ]);
    }

    /** Artist müsaitlik takvimi — önümüzdeki 7 gün */
    public function artistAvailability(User $user): JsonResponse
    {
        abort_unless(
            $user->hasAnyRole([UserRole::Artist, UserRole::KullaniciRol]),
            404
        );

        $maxDailySlots = 4;
        $days          = [];

        for ($i = 0; $i < 7; $i++) {
            $date    = Carbon::today()->addDays($i);
            $dateStr = $date->toDateString();

            $bookedCount = Appointment::query()
                ->where('assigned_artist_user_id', $user->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereDate('appointment_at', $dateStr)
                ->count();

            $available = max(0, $maxDailySlots - $bookedCount);

            if ($i === 0) {
                $label = 'Bugün';
            } elseif ($i === 1) {
                $label = 'Yarın';
            } else {
                $label = $date->locale('tr')->isoFormat('dddd');
            }

            $days[] = [
                'date'            => $dateStr,
                'label'           => $label,
                'day_name'        => $date->locale('tr')->isoFormat('dddd'),
                'available_slots' => $available,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'artist_id'           => $user->id,
                'artist_name'         => $user->fullName(),
                'response_time_hours' => $user->response_time_hours,
                'availability'        => $days,
            ],
        ]);
    }

    /** Artist değerlendirmeleri */
    public function artistReviews(User $user): JsonResponse
    {
        abort_unless(
            $user->hasAnyRole([UserRole::Artist, UserRole::Designer, UserRole::KullaniciRol]),
            404
        );

        $reviews = Review::query()
            ->with('reviewer:id,name,surname')
            ->where('artist_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->reviewsResponse($reviews);
    }

    /** Stüdyo değerlendirmeleri */
    public function studioReviews(Studio $studio): JsonResponse
    {
        $reviews = Review::query()
            ->with('reviewer:id,name,surname')
            ->where('studio_id', $studio->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->reviewsResponse($reviews);
    }

    private function reviewsResponse($reviews): JsonResponse
    {
        $total   = $reviews->count();
        $average = $total > 0 ? round($reviews->avg('rating'), 1) : null;

        $distribution = collect([5, 4, 3, 2, 1])->mapWithKeys(
            fn (int $star): array => [(string) $star => $reviews->where('rating', $star)->count()]
        );

        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary' => [
                    'average'      => $average,
                    'total'        => $total,
                    'distribution' => $distribution,
                ],
                'items' => $reviews->map(fn ($r): array => [
                    ...$this->reviewResource($r),
                ])->values(),
            ],
        ]);
    }

    public function storeArtistReview(Request $request, User $user): JsonResponse
    {
        abort_unless(
            $user->hasAnyRole([UserRole::Artist, UserRole::Designer, UserRole::KullaniciRol]),
            404
        );

        $authUser = $request->user();
        abort_if($authUser === null, 401);
        abort_if((int) $authUser->id === (int) $user->id, 422, 'Kendi profilinizi değerlendiremezsiniz.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'image_path' => ['nullable', 'string', 'max:2048'],
        ]);

        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $imagePath = Storage::disk('public')->url(
                $file->storeAs('reviews/' . $user->id, $name, 'public')
            );
        }

        $review = Review::query()->updateOrCreate(
            [
                'user_id' => $authUser->id,
                'artist_id' => $user->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'image_path' => $imagePath,
            ]
        );

        $average = Review::query()
            ->where('artist_id', $user->id)
            ->avg('rating');

        $user->forceFill([
            'rating' => $average !== null ? round((float) $average, 1) : null,
        ])->save();

        $review->load('reviewer:id,name,surname');

        return response()->json([
            'message' => 'Değerlendirmeniz kaydedildi.',
            'data' => [
                ...$this->reviewResource($review),
                'artist_rating' => $user->rating,
            ],
        ], 201);
    }

    public function storeStudioReview(Request $request, Studio $studio): JsonResponse
    {
        $authUser = $request->user();
        abort_if($authUser === null, 401);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'image_path' => ['nullable', 'string', 'max:2048'],
        ]);

        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $imagePath = Storage::disk('public')->url(
                $file->storeAs('reviews/studios/' . $studio->id, $name, 'public')
            );
        }

        $review = Review::query()->updateOrCreate(
            [
                'user_id' => $authUser->id,
                'studio_id' => $studio->id,
            ],
            [
                'artist_id' => null,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'image_path' => $imagePath,
            ]
        );

        $review->load('reviewer:id,name,surname');

        return response()->json([
            'message' => 'Stüdyo değerlendirmeniz kaydedildi.',
            'data' => $this->reviewResource($review),
        ], 201);
    }

    public function reviews(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $reviews = Review::query()
            ->with([
                'reviewer:id,name,surname,email',
                'artist:id,name,surname,role',
                'studio:id,name,location',
            ])
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => $reviews->getCollection()->map(fn (Review $review): array => $this->adminReviewResource($review))->values(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ],
        ]);
    }

    public function destroyReview(Request $request, Review $review): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $artist = $review->artist;
        $review->delete();

        if ($artist !== null) {
            $average = Review::query()
                ->where('artist_id', $artist->id)
                ->avg('rating');

            $artist->forceFill([
                'rating' => $average !== null ? round((float) $average, 1) : null,
            ])->save();
        }

        return response()->json([
            'message' => 'Yorum silindi.',
        ]);
    }

    private function reviewResource(Review $review): array
    {
        return [
            'id' => $review->id,
            'user_name' => $review->reviewer ? $review->reviewer->fullName() : 'Anonim',
            'rating' => $review->rating,
            'comment' => $review->comment,
            'image_path' => $this->reviewImageUrl($review->image_path),
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }

    private function adminReviewResource(Review $review): array
    {
        return [
            ...$this->reviewResource($review),
            'reviewer' => $review->reviewer ? [
                'id' => $review->reviewer->id,
                'name' => $review->reviewer->fullName(),
                'email' => $review->reviewer->email,
            ] : null,
            'target_type' => $review->studio_id !== null ? 'studio' : 'user',
            'target' => $review->studio_id !== null ? [
                'id' => $review->studio?->id,
                'name' => $review->studio?->name,
                'location' => $review->studio?->location,
            ] : [
                'id' => $review->artist?->id,
                'name' => $review->artist?->fullName(),
                'role' => $review->artist?->role?->value,
            ],
        ];
    }

    private function reviewImageUrl(?string $path): ?string
    {
        if ($path === null || $path === '' || str_starts_with($path, 'http')) {
            return $path;
        }

        return str_starts_with($path, 'storage/') || str_starts_with($path, '/storage/')
            ? url($path)
            : url('storage/' . $path);
    }

    /**
     * Galeri verisi eski kayıtlarda string, yeni kayıtlarda URL, bazı ekranlarda
     * obje olarak gelebiliyor. Public API her durumda mobilin doğrudan
     * gösterebileceği tam URL listesi döndürsün.
     */
    private function galleryItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(fn (mixed $item): ?string => $this->mediaUrl($this->extractImagePath($item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function extractImagePath(mixed $item): ?string
    {
        if (is_string($item)) {
            return $item;
        }

        if (is_array($item)) {
            foreach (['image_url', 'image_path', 'url', 'image', 'path', 'src'] as $key) {
                $value = $item[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function mediaUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return url($path);
        }

        if (str_starts_with($path, 'storage/')) {
            return url($path);
        }

        return url('storage/' . ltrim($path, '/'));
    }

    /** Stüdyo listesi (herkese açık, discovery için) */
    public function studios(): JsonResponse
    {
        $studios = Studio::query()
            ->with(['shop.company'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $studios->map(fn ($studio): array => [
                'gallery_images' => $this->galleryItems($studio->gallery_images ?? []),
                'portfolio'      => $this->galleryItems($studio->gallery_images ?? []),
                'id'           => $studio->id,
                'name'         => $studio->name,
                'slug'         => $studio->slug,
                'location'     => $studio->location,
                'about'        => $studio->about,
                'logo_path'    => $studio->logo_path,
                'opening_time' => $studio->opening_time ?? $studio->shop?->opening_time,
                'closing_time' => $studio->closing_time ?? $studio->shop?->closing_time,
                'shop'         => $studio->shop ? [
                    'id'           => $studio->shop->id,
                    'name'         => $studio->shop->name,
                    'location'     => $studio->shop->location,
                    'logo_path'    => $studio->shop->logo_path,
                    'opening_time' => $studio->shop->opening_time,
                    'closing_time' => $studio->shop->closing_time,
                    'company'      => $studio->shop->company ? [
                        'id'        => $studio->shop->company->id,
                        'name'      => $studio->shop->company->name,
                        'logo_path' => $studio->shop->company->logo_path,
                    ] : null,
                ] : null,
            ])->values(),
        ]);
    }

    /** Artist listesi (herkese açık) */
    public function artists(): JsonResponse
    {
        $artists = User::query()
            ->whereIn('role', [UserRole::Artist->value, UserRole::Designer->value, UserRole::KullaniciRol->value])
            ->orderBy('name')
            ->get(['id', 'name', 'surname', 'username', 'role', 'profile_image', 'bio', 'rating', 'portfolio', 'experience_years', 'specializations', 'location', 'response_time_hours']);

        return response()->json([
            'data' => $artists->map(fn ($a): array => [
                'id'                  => $a->id,
                'name'                => $a->fullName(),
                'username'            => $a->username,
                'role'                => $a->role?->value,
                'role_label'          => $a->role?->label(),
                'profile_image'       => $a->profile_image,
                'bio'                 => $a->bio,
                'location'            => $a->location,
                'experience_years'    => $a->experience_years,
                'specializations'     => $a->specializations ?? [],
                'rating'              => $a->rating,
                'response_time_hours' => $a->response_time_hours,
                'portfolio'           => $a->portfolio ?? [],
            ])->values(),
        ]);
    }
}
