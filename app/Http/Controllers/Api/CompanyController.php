<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole([UserRole::Admin, UserRole::Yonetici]), 403);

        $companies = Company::query()
            ->with('manager')
            ->withCount('studios')
            ->when(
                ! $user?->hasRole(UserRole::Admin),
                fn ($query) => $query->where('manager_user_id', $user?->id)
            )
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'code'   => 200,
            'data'   => $companies->map(fn (Company $company): array => [
                'id'                => $company->id,
                'manager_user_id'   => $company->manager_user_id,
                'name'              => $company->name,
                'address'           => $company->address,
                'phone'             => $company->phone,
                'email'             => $company->email,
                'is_active'         => $company->is_active,
                'max_studio_count'  => $company->max_studio_count,
                'studio_count'      => $company->studios_count,
                'appointment_count' => $this->companyAppointmentCount($company),
                'manager'           => $company->manager ? [
                    'id'    => $company->manager->id,
                    'name'  => $company->manager->fullName(),
                    'email' => $company->manager->email,
                ] : null,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $request->merge([
            'manager_user_id' => $request->input('manager_user_id') ?: null,
            'create_manager' => $request->boolean('create_manager'),
        ]);

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'address'          => ['nullable', 'string', 'max:500'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'string', 'email', 'max:255'],
            'manager_user_id'  => ['nullable', 'integer', 'exists:users,id'],
            'max_studio_count' => ['required', 'integer', 'min:0'],
            'create_manager'   => ['sometimes', 'boolean'],
            'manager_name'     => ['required_if:create_manager,true', 'nullable', 'string', 'max:255'],
            'manager_surname'  => ['nullable', 'string', 'max:255'],
            'manager_email'    => ['required_if:create_manager,true', 'nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'manager_phone'    => ['nullable', 'string', 'max:30'],
            'manager_password' => ['required_if:create_manager,true', 'nullable', 'string', 'min:6'],
        ]);

        if ($validated['create_manager'] ?? false) {
            $manager = User::query()->create([
                'name'     => $validated['manager_name'],
                'surname'  => $validated['manager_surname'] ?? null,
                'email'    => $validated['manager_email'],
                'phone'    => $validated['manager_phone'] ?? null,
                'password' => $validated['manager_password'],
                'role'     => UserRole::Yonetici,
            ]);

            $validated['manager_user_id'] = $manager->id;
        } else {
            $this->validateManager($validated['manager_user_id'] ?? null);
        }

        $company = Company::query()->create(collect($validated)
            ->only([
                'name',
                'address',
                'phone',
                'email',
                'manager_user_id',
                'max_studio_count',
            ])
            ->all() + ['is_active' => true]);

        return response()->json([
            'status'  => 'success',
            'code'    => 201,
            'message' => 'Şirket oluşturuldu.',
            'data'    => $company->load('manager'),
        ], 201);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        if ($request->has('manager_user_id')) {
            $request->merge([
                'manager_user_id' => $request->input('manager_user_id') ?: null,
            ]);
        }

        $validated = $request->validate([
            'name'             => ['sometimes', 'string', 'max:255'],
            'address'          => ['nullable', 'string', 'max:500'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'string', 'email', 'max:255'],
            'manager_user_id'  => ['nullable', 'integer', 'exists:users,id'],
            'about'            => ['nullable', 'string', 'max:5000'],
            'website'          => ['nullable', 'string', 'url', 'max:255'],
            'is_active'        => ['sometimes', 'boolean'],
            'max_studio_count' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->validateManager($validated['manager_user_id'] ?? null);

        $company->fill($validated)->save();

        return response()->json([
            'status'  => 'success',
            'code'    => 200,
            'message' => 'Şirket güncellendi.',
            'data'    => $company->fresh()->load('manager'),
        ]);
    }

    // ── Özel ──────────────────────────────────────────────────

    private function companyAppointmentCount(Company $company): int
    {
        $studioIds = Studio::query()
            ->where('company_id', $company->id)
            ->pluck('id');

        return Appointment::query()->whereIn('studio_id', $studioIds)->count();
    }

    private function validateManager(?int $managerUserId): void
    {
        if ($managerUserId === null) {
            return;
        }

        abort_unless(
            User::query()->findOrFail($managerUserId)->hasRole(UserRole::Yonetici),
            422
        );
    }
}
