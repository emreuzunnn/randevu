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
            ->with(['company.manager', 'manager', 'supervisor', 'studios'])
            ->withCount('studios')
            ->when(
                ! $user?->hasRole(UserRole::Admin),
                fn ($query) => $query->where(function ($query) use ($user): void {
                    $query->whereHas('company', fn ($companyQuery) => $companyQuery->where('manager_user_id', $user?->id))
                        ->orWhere('manager_user_id', $user?->id);
                })
            )
            ->orderBy('name')
            ->get();

        $managers = User::query()
            ->where('role', UserRole::Supervisor->value)
            ->orderBy('name')
            ->get();

        return view('admin.shops.index', compact('shops', 'managers'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'supervisor_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->validateSupervisor($validated['supervisor_user_id'] ?? null);

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
            'supervisor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->validateSupervisor($validated['supervisor_user_id'] ?? null);

        $shop->fill($validated)->save();

        return redirect()->route('admin.shops.index')->with('status', 'Dukkan guncellendi.');
    }

    private function validateSupervisor(?int $supervisorUserId): void
    {
        if ($supervisorUserId === null) {
            return;
        }

        abort_unless(
            User::query()->findOrFail($supervisorUserId)->hasRole(UserRole::Supervisor),
            422
        );
    }
}
