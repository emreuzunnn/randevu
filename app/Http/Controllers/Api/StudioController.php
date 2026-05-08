<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudioController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        $studios = Studio::query()
            ->with('shop')
            ->when(
                ! $user?->hasRole(\App\Enums\UserRole::Admin),
                fn ($query) => $query->whereIn('id', $user?->accessibleStudioIds() ?? [])
            )
            ->withCount([
                'appointments',
                'users as total_staff_count',
                'users as active_staff_count' => fn ($query) => $query->where('studio_user.is_active', true),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $studios->map(fn (Studio $studio): array => [
                'id' => $studio->id,
                'name' => $studio->name,
                'location' => $studio->location,
                'slug' => $studio->slug,
                'logo_path' => $studio->logo_path,
                'shop' => $studio->shop ? [
                    'id' => $studio->shop->id,
                    'name' => $studio->shop->name,
                ] : null,
                'total_staff_count' => $studio->total_staff_count,
                'active_staff_count' => $studio->active_staff_count,
                'appointments_count' => $studio->appointments_count,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]), 403);

        $validated = $request->validate([
            'shop_id'                    => ['required', 'integer', 'exists:shops,id'],
            'name'                       => ['required', 'string', 'max:255'],
            'location'                   => ['nullable', 'string', 'max:255'],
        ]);

        $shop = Shop::query()->with('company')->findOrFail($validated['shop_id']);

        abort_unless($request->user()?->canManageShop($shop), 403);

        // Şirket stüdyo limiti kontrolü
        if ($shop->company !== null && ! $shop->company->canAddStudio()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 422,
                'message' => 'Stüdyo limitinize ulaştınız. Daha fazla stüdyo oluşturmak için lütfen admin ile iletişime geçin.',
                'data'    => [
                    'current' => $shop->company->currentStudioCount(),
                    'limit'   => $shop->company->max_studio_count,
                ],
            ], 422);
        }

        $studio = Studio::query()->create([
            'shop_id'                   => $shop->id,
            'name'                      => $validated['name'],
            'location'                  => $validated['location'] ?? null,
            'slug'                      => Str::slug($validated['name']) . '-' . Str::random(5),
        ]);

        return response()->json([
            'status'  => 'success',
            'code'    => 201,
            'message' => 'Stüdyo oluşturuldu.',
            'data'    => [
                'id'       => $studio->id,
                'name'     => $studio->name,
                'location' => $studio->location,
                'slug'     => $studio->slug,
                'shop_id'  => $studio->shop_id,
            ],
        ], 201);
    }

    public function destroy(Request $request, Studio $studio): JsonResponse
    {
        abort_unless($request->user()?->hasRole(\App\Enums\UserRole::Admin), 403);

        $studio->delete();

        return response()->json(['message' => 'Stüdyo silindi.']);
    }

    public function update(Request $request, Studio $studio): JsonResponse
    {
        abort_unless($request->user()?->canManageStudio($studio), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        $studio->fill($validated)->save();

        return response()->json([
            'message' => 'Studyo guncellendi.',
            'data' => $studio->only([
                'id',
                'name',
                'location',
                'slug',
                'logo_path',
                'shop_id',
            ]),
        ]);
    }
}
