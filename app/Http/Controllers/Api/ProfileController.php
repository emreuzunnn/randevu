<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Shop;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    // ── Studio Profili ────────────────────────────────────────────────────────

    public function studioProfile(Studio $studio): JsonResponse
    {
        $user = request()->user();
        abort_unless($user?->canManageStudio($studio) || $user?->hasRole(UserRole::Admin), 403);

        $stats = Appointment::query()
            ->where('studio_id', $studio->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $shop = $studio->shop()->with('company')->first();

        $studioPortfolio = $this->galleryItems($studio->gallery_images ?? []);
        $shopPortfolio = $this->galleryItems($shop?->gallery_images ?? []);

        return response()->json([
            'status' => 'success',
            'code'   => 200,
            'data'   => [
                'id'           => $studio->id,
                'name'         => $studio->name,
                'slug'         => $studio->slug,
                'location'     => $studio->location,
                'about'        => $studio->about,
                'logo_path'    => $studio->logo_path,
                'opening_time' => $studio->opening_time,
                'closing_time' => $studio->closing_time,
                'gallery_images' => $studioPortfolio,
                'portfolio'      => $studioPortfolio,
                'aggregated_gallery' => array_values(array_unique(array_merge($studioPortfolio, $shopPortfolio))),
                'shop'         => $shop ? [
                    'id'           => $shop->id,
                    'name'         => $shop->name,
                    'logo_path'    => $shop->logo_path,
                    'opening_time' => $shop->opening_time,
                    'closing_time' => $shop->closing_time,
                    'gallery_images' => $shopPortfolio,
                    'company'      => $shop->company ? [
                        'id'        => $shop->company->id,
                        'name'      => $shop->company->name,
                        'logo_path' => $shop->company->logo_path,
                    ] : null,
                ] : null,
                'appointment_stats' => [
                    'open'      => (int) ($stats['confirmed'] ?? 0),
                    'cancelled' => (int) ($stats['cancelled'] ?? 0),
                    'completed' => (int) ($stats['completed'] ?? 0),
                    'total'     => $stats->sum(),
                ],
            ],
        ]);
    }

    // ── Şube (Shop) Profili ───────────────────────────────────────────────────

    public function shopProfile(Shop $shop): JsonResponse
    {
        $user = request()->user();
        abort_unless($user?->canManageShop($shop) || $user?->hasRole(UserRole::Admin), 403);

        $studios = Studio::query()
            ->where('shop_id', $shop->id)
            ->withCount('appointments')
            ->get();

        $studioIds = $studios->pluck('id');

        $appointmentStats = Appointment::query()
            ->whereIn('studio_id', $studioIds)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $galleryImages = $studios->flatMap(fn ($s) => $this->galleryItems($s->gallery_images ?? []))->values()->all();

        $company = $shop->company;

        return response()->json([
            'status' => 'success',
            'code'   => 200,
            'data'   => [
                'id'           => $shop->id,
                'name'         => $shop->name,
                'location'     => $shop->location,
                'about'        => $shop->about,
                'logo_path'    => $shop->logo_path,
                'opening_time' => $shop->opening_time,
                'closing_time' => $shop->closing_time,
                'gallery_images' => $this->galleryItems($shop->gallery_images ?? []),
                'company'      => $company ? [
                    'id'        => $company->id,
                    'name'      => $company->name,
                    'logo_path' => $company->logo_path,
                    'about'     => $company->about,
                    'website'   => $company->website,
                ] : null,
                'studios' => $studios->map(fn (Studio $s): array => [
                    'id'             => $s->id,
                    'name'           => $s->name,
                    'location'       => $s->location,
                    'about'          => $s->about,
                    'logo_path'      => $s->logo_path,
                    'opening_time'   => $s->opening_time,
                    'closing_time'   => $s->closing_time,
                    'gallery_images' => $this->galleryItems($s->gallery_images ?? []),
                    'appointment_count' => $s->appointments_count,
                ])->values(),
                'aggregated_gallery' => $galleryImages,
                'appointment_stats' => [
                    'open'      => (int) ($appointmentStats['confirmed'] ?? 0),
                    'cancelled' => (int) ($appointmentStats['cancelled'] ?? 0),
                    'completed' => (int) ($appointmentStats['completed'] ?? 0),
                    'total'     => $appointmentStats->sum(),
                ],
            ],
        ]);
    }

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

    // ── Şirket Profili ────────────────────────────────────────────────────────

    public function companyProfile(Company $company): JsonResponse
    {
        abort_unless(request()->user()?->hasRole(UserRole::Admin), 403);

        $shops = Shop::query()
            ->where('company_id', $company->id)
            ->with(['studios'])
            ->get();

        $studioIds = $shops->flatMap(fn ($sh) => $sh->studios->pluck('id'));

        $appointmentStats = Appointment::query()
            ->whereIn('studio_id', $studioIds)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $allGallery = collect([$company->gallery_images ?? []])
            ->merge($shops->map(fn ($sh) => $sh->gallery_images ?? []))
            ->merge($shops->flatMap(fn ($sh) => $sh->studios->map(fn ($s) => $s->gallery_images ?? [])))
            ->flatten()
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'code'   => 200,
            'data'   => [
                'id'             => $company->id,
                'name'           => $company->name,
                'logo_path'      => $company->logo_path,
                'about'          => $company->about,
                'address'        => $company->address,
                'phone'          => $company->phone,
                'email'          => $company->email,
                'website'        => $company->website,
                'gallery_images' => $company->gallery_images ?? [],
                'aggregated_gallery' => $allGallery,
                'shops' => $shops->map(fn (Shop $sh): array => [
                    'id'           => $sh->id,
                    'name'         => $sh->name,
                    'location'     => $sh->location,
                    'about'        => $sh->about,
                    'logo_path'    => $sh->logo_path,
                    'opening_time' => $sh->opening_time,
                    'closing_time' => $sh->closing_time,
                    'gallery_images' => $sh->gallery_images ?? [],
                    'studios'      => $sh->studios->map(fn (Studio $s): array => [
                        'id'             => $s->id,
                        'name'           => $s->name,
                        'location'       => $s->location,
                        'about'          => $s->about,
                        'logo_path'      => $s->logo_path,
                        'opening_time'   => $s->opening_time,
                        'closing_time'   => $s->closing_time,
                        'gallery_images' => $s->gallery_images ?? [],
                    ])->values(),
                ])->values(),
                'appointment_stats' => [
                    'open'      => (int) ($appointmentStats['confirmed'] ?? 0),
                    'cancelled' => (int) ($appointmentStats['cancelled'] ?? 0),
                    'completed' => (int) ($appointmentStats['completed'] ?? 0),
                    'total'     => $appointmentStats->sum(),
                ],
            ],
        ]);
    }
}
