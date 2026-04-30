<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                'notification_lead_minutes' => $studio->notification_lead_minutes,
                'shop' => $studio->shop ? [
                    'id' => $studio->shop->id,
                    'name' => $studio->shop->name,
                ] : null,
                'active_staff_count' => $studio->active_staff_count,
                'appointments_count' => $studio->appointments_count,
            ])->values(),
        ]);
    }

    public function update(Request $request, Studio $studio): JsonResponse
    {
        abort_unless($request->user()?->canManageStudio($studio), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'notification_lead_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
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
                'notification_lead_minutes',
                'shop_id',
            ]),
        ]);
    }
}
