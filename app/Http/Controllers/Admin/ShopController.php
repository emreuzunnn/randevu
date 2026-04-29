<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole([UserRole::Admin, UserRole::Yonetici]), 403);

        $shops = Shop::query()
            ->with(['manager', 'studios'])
            ->withCount('studios')
            ->when(
                ! $user?->hasRole(UserRole::Admin),
                fn ($query) => $query->where('manager_user_id', $user?->id)
            )
            ->orderBy('name')
            ->get();

        $managers = User::query()
            ->whereIn('role', [UserRole::Yonetici->value, UserRole::Supervisor->value])
            ->orderBy('name')
            ->get();

        return view('admin.shops.index', compact('shops', 'managers'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        Shop::query()->create($validated + ['is_active' => true]);

        return redirect()->route('admin.shops.index')->with('status', 'Dukkan olusturuldu.');
    }

    public function update(Request $request, Shop $shop): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole([UserRole::Admin, UserRole::Yonetici]), 403);
        abort_unless($request->user()?->canManageShop($shop), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! $request->user()?->hasRole(UserRole::Admin)) {
            unset($validated['manager_user_id']);
        }

        $shop->fill($validated)->save();

        return redirect()->route('admin.shops.index')->with('status', 'Dukkan guncellendi.');
    }
}
