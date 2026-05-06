<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(auth()->user()?->hasRole(UserRole::Admin), 403);

        $companies = Company::query()
            ->withCount(['shops', 'studios'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'code'   => 200,
            'data'   => $companies->map(fn (Company $company): array => [
                'id'                => $company->id,
                'name'              => $company->name,
                'address'           => $company->address,
                'phone'             => $company->phone,
                'email'             => $company->email,
                'is_active'         => $company->is_active,
                'max_shop_count'    => $company->max_shop_count,
                'max_studio_count'  => $company->max_studio_count,
                'shop_count'        => $company->shops_count,
                'studio_count'      => $company->studios_count,
                'appointment_count' => $this->companyAppointmentCount($company),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'address'          => ['nullable', 'string', 'max:500'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'string', 'email', 'max:255'],
            'max_shop_count'   => ['required', 'integer', 'min:0'],
            'max_studio_count' => ['required', 'integer', 'min:0'],
        ]);

        $company = Company::query()->create($validated + ['is_active' => true]);

        return response()->json([
            'status'  => 'success',
            'code'    => 201,
            'message' => 'Şirket oluşturuldu.',
            'data'    => $company,
        ], 201);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $validated = $request->validate([
            'name'             => ['sometimes', 'string', 'max:255'],
            'address'          => ['nullable', 'string', 'max:500'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'string', 'email', 'max:255'],
            'is_active'        => ['sometimes', 'boolean'],
            'max_shop_count'   => ['sometimes', 'integer', 'min:0'],
            'max_studio_count' => ['sometimes', 'integer', 'min:0'],
        ]);

        $company->fill($validated)->save();

        return response()->json([
            'status'  => 'success',
            'code'    => 200,
            'message' => 'Şirket güncellendi.',
            'data'    => $company->fresh(),
        ]);
    }

    // ── Özel ──────────────────────────────────────────────────

    private function companyAppointmentCount(Company $company): int
    {
        $studioIds = Studio::query()
            ->whereHas('shop', fn ($q) => $q->where('company_id', $company->id))
            ->pluck('id');

        return Appointment::query()->whereIn('studio_id', $studioIds)->count();
    }
}
