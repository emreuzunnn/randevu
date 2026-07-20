<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
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
            ->with('company')
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
                'instagram' => $studio->instagram,
                'facebook' => $studio->facebook,
                'company' => $studio->company ? [
                    'id' => $studio->company->id,
                    'name' => $studio->company->name,
                ] : null,
                'total_staff_count' => $studio->total_staff_count,
                'active_staff_count' => $studio->active_staff_count,
                'appointments_count' => $studio->appointments_count,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole([UserRole::Admin, UserRole::Yonetici]), 403);

        $validated = $request->validate([
            'company_id'                 => ['nullable', 'integer', 'exists:companies,id'],
            'name'                       => ['required', 'string', 'max:255'],
            'location'                   => ['nullable', 'string', 'max:255'],
            'instagram'                  => ['nullable', 'string', 'max:255'],
            'facebook'                   => ['nullable', 'string', 'max:255'],
        ]);

        $company = isset($validated['company_id'])
            ? Company::query()->findOrFail($validated['company_id'])
            : $user->managedCompanies()->first();

        abort_if($company === null, 422, 'Stüdyo için bir şirket bulunamadı.');
        abort_unless(
            $user->hasRole(UserRole::Admin)
                || (int) $company->manager_user_id === (int) $user->id,
            403
        );

        if (! $company->canAddStudio()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 422,
                'message' => 'Stüdyo limitinize ulaştınız. Daha fazla stüdyo oluşturmak için lütfen admin ile iletişime geçin.',
                'data'    => [
                    'current' => $company->currentStudioCount(),
                    'limit'   => $company->max_studio_count,
                ],
            ], 422);
        }

        $studio = Studio::query()->create([
            'company_id'                => $company->id,
            'owner_user_id'             => $request->user()->id,
            'name'                      => $validated['name'],
            'location'                  => $validated['location'] ?? null,
            'instagram'                 => $validated['instagram'] ?? null,
            'facebook'                  => $validated['facebook'] ?? null,
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
                'company_id' => $studio->company_id,
                'instagram' => $studio->instagram,
                'facebook' => $studio->facebook,
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
            'name'         => ['sometimes', 'string', 'max:255'],
            'location'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'about'        => ['sometimes', 'nullable', 'string', 'max:5000'],
            'opening_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'closing_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'logo_path'    => ['sometimes', 'nullable', 'string', 'max:2048'],
            'instagram'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'facebook'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_id'   => ['sometimes', 'integer', 'exists:companies,id'],
        ]);

        if (isset($validated['company_id'])) {
            $company = Company::query()->findOrFail($validated['company_id']);
            abort_unless(
                $request->user()?->hasRole(UserRole::Admin)
                    || (int) $company->manager_user_id === (int) $request->user()?->id,
                403
            );
        }

        $studio->fill($validated)->save();

        return response()->json([
            'message' => 'Stüdyo güncellendi.',
            'data' => $studio->only([
                'id',
                'name',
                'location',
                'slug',
                'about',
                'logo_path',
                'instagram',
                'facebook',
                'opening_time',
                'closing_time',
                'gallery_images',
                'company_id',
            ]),
        ]);
    }
}
