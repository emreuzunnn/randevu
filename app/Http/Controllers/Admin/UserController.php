<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Services\StudioStaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole([UserRole::Admin, UserRole::Yonetici]), 403);
        $studioId = $request->integer('studio_id');
        $studios = Studio::query()
            ->with('shop')
            ->when(
                ! $user?->hasRole(UserRole::Admin),
                fn ($query) => $query->whereIn('id', $user?->accessibleStudioIds() ?? [])
            )
            ->orderBy('name')
            ->get();
        $selectedStudio = $studioId > 0 ? $studios->firstWhere('id', $studioId) : $studios->first();

        $users = collect();

        if ($selectedStudio !== null) {
            $users = $selectedStudio->users()->orderBy('users.name')->get();
        }

        return view('admin.users.index', [
            'studios' => $studios,
            'selectedStudio' => $selectedStudio,
            'users' => $users,
            'roles' => [
                ...($user?->hasRole(UserRole::Admin) ? [UserRole::Admin, UserRole::Yonetici] : []),
                UserRole::Supervisor,
                UserRole::Sofor,
                UserRole::Calisan,
            ],
        ]);
    }

    public function store(Request $request, StudioStaffService $staffService): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole([UserRole::Admin, UserRole::Yonetici]), 403);

        $validated = $request->validate([
            'studio_id' => ['required', 'exists:studios,id'],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'role' => ['required', 'in:admin,yonetici,supervisor,sofor,calisan'],
            'email' => ['required', 'email'],
            'password' => ['required', 'digits:6', 'confirmed'],
        ]);

        $studio = Studio::query()->findOrFail($validated['studio_id']);
        abort_unless($request->user()?->canManageStudio($studio), 403);

        if (! $request->user()?->hasRole(UserRole::Admin) && in_array($validated['role'], ['admin', 'yonetici'], true)) {
            abort(403);
        }

        $staffService->createOrAttach($studio, UserRole::fromValue($validated['role']), $validated);

        return redirect()
            ->route('admin.users.index', ['studio_id' => $studio->id])
            ->with('status', 'Kullanici olusturuldu.');
    }
}
