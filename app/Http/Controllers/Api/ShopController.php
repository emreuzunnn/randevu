<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $shops = Shop::query()
            ->with(['manager', 'studios'])
            ->when(
                ! $user->hasRole(UserRole::Admin),
                fn ($query) => $query->where('manager_user_id', $user->id)
            )
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $shops->map(fn (Shop $shop): array => [
                'id'         => $shop->id,
                'company_id' => $shop->company_id,
                'name'       => $shop->name,
                'location'   => $shop->location,
                'is_active'  => (bool) $shop->is_active,
                'manager'    => $shop->manager ? [
                    'id'    => $shop->manager->id,
                    'name'  => $shop->manager->fullName(),
                    'email' => $shop->manager->email,
                    'role'  => $shop->manager->role?->value,
                ] : null,
                'studios' => $shop->studios->map(fn ($studio): array => [
                    'id'   => $studio->id,
                    'name' => $studio->name,
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $validated = $request->validate([
            'company_id'      => ['required', 'integer', 'exists:companies,id'],
            'name'            => ['required', 'string', 'max:255'],
            'location'        => ['nullable', 'string', 'max:255'],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);

        // Şirket dükkan limiti kontrolü
        $company = Company::query()->findOrFail($validated['company_id']);

        if (! $company->canAddShop()) {
            return response()->json([
                'message' => 'Dükkan limitinize ulaştınız. Daha fazla dükkan oluşturmak için lütfen admin ile iletişime geçin.',
                'data'    => [
                    'current' => $company->currentShopCount(),
                    'limit'   => $company->max_shop_count,
                ],
            ], 422);
        }

        if (isset($validated['manager_user_id'])) {
            $manager = User::query()->findOrFail($validated['manager_user_id']);
            abort_unless($manager->hasAnyRole([UserRole::Yonetici, UserRole::Supervisor]), 422);
        }

        $shop = Shop::query()->create([
            'company_id'      => $company->id,
            'name'            => $validated['name'],
            'location'        => $validated['location'] ?? null,
            'manager_user_id' => $validated['manager_user_id'] ?? null,
            'is_active'       => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Dükkan oluşturuldu.',
            'data'    => $shop->load('manager'),
        ], 201);
    }

    public function update(Request $request, Shop $shop): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->canManageShop($shop), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['manager_user_id'])) {
            $manager = User::query()->findOrFail($validated['manager_user_id']);
            abort_unless($manager->hasAnyRole([UserRole::Yonetici, UserRole::Supervisor]), 422);
        }

        if (! $user?->hasRole(UserRole::Admin)) {
            unset($validated['manager_user_id']);
        }

        $shop->fill($validated)->save();

        return response()->json([
            'message' => 'Dukkan guncellendi.',
            'data' => $shop->fresh()->load('manager'),
        ]);
    }
}
