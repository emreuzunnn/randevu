<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        abort_unless($user?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]), 403);

        $studios = Studio::query()
            ->with('shop')
            ->when(
                ! $user?->hasRole(\App\Enums\UserRole::Admin),
                fn ($query) => $query->whereIn('id', $user?->accessibleStudioIds() ?? [])
            )
            ->withCount([
                'appointments',
                'users as active_staff_count' => fn ($query) => $query->where('studio_user.is_active', true),
            ])
            ->orderBy('name')
            ->get();

        return view('admin.studios.index', compact('studios'));
    }

    public function update(Request $request, Studio $studio): RedirectResponse
    {
        abort_unless($request->user()?->canManageStudio($studio), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:2048'],
            'notification_lead_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);

        $studio->fill($validated)->save();

        return redirect()->route('admin.studios.index')->with('status', 'Studyo guncellendi.');
    }
}
