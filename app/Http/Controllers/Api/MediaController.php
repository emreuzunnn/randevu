<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Shop;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    // ── Yardımcı ─────────────────────────────────────────────────────────────

    private function storeImage(Request $request, string $field, string $folder): string
    {
        $file = $request->file($field);
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $name, 'public');
        return Storage::disk('public')->url($path);
    }

    private function deleteOldImage(?string $url): void
    {
        if (! $url) {
            return;
        }
        $relative = ltrim(parse_url($url, PHP_URL_PATH), '/');
        $relative = preg_replace('#^storage/#', '', $relative);
        if ($relative) {
            Storage::disk('public')->delete($relative);
        }
    }

    // ── Şirket Logo ──────────────────────────────────────────────────────────

    public function uploadCompanyLogo(Request $request, Company $company): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $this->deleteOldImage($company->logo_path);
        $url = $this->storeImage($request, 'logo', 'logos/companies');
        $company->update(['logo_path' => $url]);

        return response()->json([
            'status'   => 'success',
            'message'  => 'Şirket logosu güncellendi.',
            'logo_path' => $url,
        ]);
    }

    // ── Şube Logo ─────────────────────────────────────────────────────────────

    public function uploadShopLogo(Request $request, Shop $shop): JsonResponse
    {
        abort_unless($request->user()?->canManageShop($shop) || $request->user()?->hasRole(UserRole::Admin), 403);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $this->deleteOldImage($shop->logo_path);
        $url = $this->storeImage($request, 'logo', 'logos/shops');
        $shop->update(['logo_path' => $url]);

        return response()->json([
            'status'   => 'success',
            'message'  => 'Şube logosu güncellendi.',
            'logo_path' => $url,
        ]);
    }

    // ── Stüdyo Logo ──────────────────────────────────────────────────────────

    public function uploadStudioLogo(Request $request, Studio $studio): JsonResponse
    {
        abort_unless($request->user()?->canManageStudio($studio), 403);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $this->deleteOldImage($studio->logo_path);
        $url = $this->storeImage($request, 'logo', 'logos/studios');
        $studio->update(['logo_path' => $url]);

        return response()->json([
            'status'   => 'success',
            'message'  => 'Stüdyo logosu güncellendi.',
            'logo_path' => $url,
        ]);
    }

    // ── Şirket Galeri ────────────────────────────────────────────────────────

    public function addCompanyGalleryImage(Request $request, Company $company): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ]);

        $url = $this->storeImage($request, 'image', 'gallery/companies/' . $company->id);
        $images = array_merge($company->gallery_images ?? [], [$url]);
        $company->update(['gallery_images' => $images]);

        return response()->json([
            'status'         => 'success',
            'message'        => 'Görsel eklendi.',
            'gallery_images' => $images,
        ]);
    }

    public function removeCompanyGalleryImage(Request $request, Company $company): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $request->validate([
            'url' => ['required', 'string'],
        ]);

        $this->deleteOldImage($request->input('url'));
        $images = array_values(array_filter(
            $company->gallery_images ?? [],
            fn ($u) => $u !== $request->input('url')
        ));
        $company->update(['gallery_images' => $images]);

        return response()->json([
            'status'         => 'success',
            'message'        => 'Görsel silindi.',
            'gallery_images' => $images,
        ]);
    }

    // ── Şube Galeri ──────────────────────────────────────────────────────────

    public function addShopGalleryImage(Request $request, Shop $shop): JsonResponse
    {
        abort_unless($request->user()?->canManageShop($shop) || $request->user()?->hasRole(UserRole::Admin), 403);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ]);

        $url = $this->storeImage($request, 'image', 'gallery/shops/' . $shop->id);
        $images = array_merge($shop->gallery_images ?? [], [$url]);
        $shop->update(['gallery_images' => $images]);

        return response()->json([
            'status'         => 'success',
            'message'        => 'Görsel eklendi.',
            'gallery_images' => $images,
        ]);
    }

    public function removeShopGalleryImage(Request $request, Shop $shop): JsonResponse
    {
        abort_unless($request->user()?->canManageShop($shop) || $request->user()?->hasRole(UserRole::Admin), 403);

        $request->validate([
            'url' => ['required', 'string'],
        ]);

        $this->deleteOldImage($request->input('url'));
        $images = array_values(array_filter(
            $shop->gallery_images ?? [],
            fn ($u) => $u !== $request->input('url')
        ));
        $shop->update(['gallery_images' => $images]);

        return response()->json([
            'status'         => 'success',
            'message'        => 'Görsel silindi.',
            'gallery_images' => $images,
        ]);
    }

    // ── Stüdyo Galeri ────────────────────────────────────────────────────────

    public function addStudioGalleryImage(Request $request, Studio $studio): JsonResponse
    {
        abort_unless($request->user()?->canManageStudio($studio), 403);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ]);

        $url = $this->storeImage($request, 'image', 'gallery/studios/' . $studio->id);
        $images = array_merge($studio->gallery_images ?? [], [$url]);
        $studio->update(['gallery_images' => $images]);

        return response()->json([
            'status'         => 'success',
            'message'        => 'Görsel eklendi.',
            'gallery_images' => $images,
        ]);
    }

    public function removeStudioGalleryImage(Request $request, Studio $studio): JsonResponse
    {
        abort_unless($request->user()?->canManageStudio($studio), 403);

        $request->validate([
            'url' => ['required', 'string'],
        ]);

        $this->deleteOldImage($request->input('url'));
        $images = array_values(array_filter(
            $studio->gallery_images ?? [],
            fn ($u) => $u !== $request->input('url')
        ));
        $studio->update(['gallery_images' => $images]);

        return response()->json([
            'status'         => 'success',
            'message'        => 'Görsel silindi.',
            'gallery_images' => $images,
        ]);
    }

    // ── Kullanıcı Avatar (Profil Fotoğrafı) ──────────────────────────────────

    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $this->deleteOldImage($user->profile_image);
        $url = $this->storeImage($request, 'avatar', 'avatars/' . $user->id);
        $user->update(['profile_image' => $url]);

        return response()->json([
            'status'        => 'success',
            'message'       => 'Profil fotoğrafı güncellendi.',
            'profile_image' => $url,
        ]);
    }

    // ── Portfolio Görsel Yükleme ──────────────────────────────────────────────

    /** Portfolyo için görsel yükler ve URL döndürür (portfolyoya kaydetmez) */
    public function uploadPortfolioImage(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ]);

        $url = $this->storeImage($request, 'image', 'portfolio/' . $user->id);

        return response()->json([
            'status'     => 'success',
            'message'    => 'Görsel yüklendi.',
            'image_path' => $url,
        ]);
    }
}
