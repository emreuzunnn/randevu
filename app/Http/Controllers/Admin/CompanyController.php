<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->hasRole(UserRole::Admin), 403);

        $companies = Company::query()
            ->withCount(['shops', 'studios'])
            ->orderBy('name')
            ->get();

        return view('admin.companies.index', compact('companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'address'          => ['nullable', 'string', 'max:500'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'string', 'email', 'max:255'],
            'manager_user_id'  => ['nullable', 'integer', 'exists:users,id'],
            'max_shop_count'   => ['required', 'integer', 'min:0'],
            'max_studio_count' => ['required', 'integer', 'min:0'],
        ]);

        $this->validateManager($validated['manager_user_id'] ?? null);

        Company::query()->create($validated + ['is_active' => true]);

        return redirect()->route('admin.companies.index')
            ->with('status', 'Şirket oluşturuldu.');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $validated = $request->validate([
            'name'             => ['sometimes', 'string', 'max:255'],
            'address'          => ['nullable', 'string', 'max:500'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'string', 'email', 'max:255'],
            'manager_user_id'  => ['nullable', 'integer', 'exists:users,id'],
            'is_active'        => ['sometimes', 'boolean'],
            'max_shop_count'   => ['sometimes', 'integer', 'min:0'],
            'max_studio_count' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->validateManager($validated['manager_user_id'] ?? null);

        $company->fill($validated)->save();

        return redirect()->route('admin.companies.index')
            ->with('status', "\"$company->name\" güncellendi.");
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
