<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudioStaffService
{
    /**
     * @param  array{name:string,surname?:string|null,phone?:string|null,email:string,password?:string|null}  $attributes
     * @return array{user:User,studio_role:string,action:string}
     */
    public function createOrAttach(Studio $studio, UserRole $role, array $attributes): array
    {
        return DB::transaction(function () use ($studio, $role, $attributes): array {
            $existingUser = User::query()
                ->where('email', $attributes['email'])
                ->first();

            if ($existingUser !== null) {
                if ($existingUser->belongsToStudio($studio)) {
                    throw ValidationException::withMessages([
                        'email' => ['Bu kullanici zaten bu studyoya bagli.'],
                    ]);
                }

                $existingUser->fill([
                    'name' => $attributes['name'],
                    'surname' => $attributes['surname'] ?? $existingUser->surname,
                    'phone' => $attributes['phone'] ?? $existingUser->phone,
                ]);

                if (! empty($attributes['password'])) {
                    $existingUser->password = $attributes['password'];
                }

                $existingUser->role = $role;

                $existingUser->save();

                $studio->users()->attach($existingUser->id, [
                    'role' => $role->value,
                    'work_status' => 'working',
                    'is_active' => true,
                    'joined_at' => now(),
                ]);

                return [
                    'user' => $existingUser->fresh(),
                    'studio_role' => $role->value,
                    'action' => 'attached_existing_user',
                ];
            }

            if (blank($attributes['password'] ?? null)) {
                throw ValidationException::withMessages([
                    'password' => ['Yeni kullanici olusturmak icin sifre zorunludur.'],
                ]);
            }

            $user = User::query()->create([
                'name' => $attributes['name'],
                'surname' => $attributes['surname'] ?? null,
                'email' => $attributes['email'],
                'phone' => $attributes['phone'] ?? null,
                'password' => $attributes['password'],
                'role' => $role,
            ]);

            $studio->users()->attach($user->id, [
                'role' => $role->value,
                'work_status' => 'working',
                'is_active' => true,
                'joined_at' => now(),
            ]);

            return [
                'user' => $user,
                'studio_role' => $role->value,
                'action' => 'created_new_user',
            ];
        });
    }

    /**
     * @param  array{name?:string,surname?:string,phone?:string,email?:string,password?:string|null,is_active?:bool,status?:string,role?:string,profile_image?:string|null}  $attributes
     */
    public function updateMembership(Studio $studio, User $user, UserRole $role, array $attributes): User
    {
        $membership = $studio->users()
            ->where('users.id', $user->id)
            ->wherePivot('role', $role->value)
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'user' => ['Bu kullanici bu rol ile studyoya bagli degil.'],
            ]);
        }

        if (array_key_exists('email', $attributes)) {
            $emailExists = User::query()
                ->where('email', $attributes['email'])
                ->whereKeyNot($user->id)
                ->exists();

            if ($emailExists) {
                throw ValidationException::withMessages([
                    'email' => ['Bu email zaten kullanimda.'],
                ]);
            }
        }

        $user->fill(collect($attributes)->only(['name', 'surname', 'phone', 'email', 'profile_image'])->all());

        if (! empty($attributes['password'])) {
            $user->password = $attributes['password'];
        }

        $user->save();

        if (array_key_exists('is_active', $attributes)) {
            $pivotUpdates['is_active'] = (bool) $attributes['is_active'];
            $pivotUpdates['left_at'] = $attributes['is_active'] ? null : now();
        }

        if (array_key_exists('status', $attributes)) {
            $pivotUpdates['work_status'] = $attributes['status'];
        }

        if (array_key_exists('role', $attributes)) {
            $newRole = UserRole::fromValue($attributes['role']);
            $pivotUpdates['role'] = $newRole->value;
            $user->role = $newRole;
            $user->save();
        }

        if (! empty($pivotUpdates ?? [])) {
            $studio->users()->updateExistingPivot($user->id, $pivotUpdates);
        }

        return $user->fresh();
    }

    public function deactivateMembership(Studio $studio, User $user, UserRole $role): void
    {
        $membership = $studio->users()
            ->where('users.id', $user->id)
            ->wherePivot('role', $role->value)
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'user' => ['Bu kullanici bu rol ile studyoya bagli degil.'],
            ]);
        }

        $studio->users()->updateExistingPivot($user->id, [
            'is_active' => false,
            'left_at' => now(),
        ]);
    }
}
