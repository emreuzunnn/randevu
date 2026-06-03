<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\StudioStaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudioStaffService
{
    public function __construct(
        private readonly FcmService $fcmService,
    ) {}

    /**
     * @param  array{name:string,surname?:string|null,phone?:string|null,email:string,password?:string|null}  $attributes
     * @return array{user:User,studio_role:string,action:string,invitation?:StudioStaffInvitation}
     */
    public function createOrAttach(
        Studio $studio,
        UserRole $role,
        array $attributes,
        ?User $invitedBy = null
    ): array
    {
        return DB::transaction(function () use ($studio, $role, $attributes, $invitedBy): array {
            $this->ensureStudioProfessionalSlotAvailable($studio, $role);

            $existingUser = User::query()
                ->where('email', $attributes['email'])
                ->first();

            if ($existingUser !== null) {
                if (in_array($role, UserRole::studioRoles(), true)) {
                    return $this->inviteRegisteredStaff(
                        $studio,
                        $role,
                        $existingUser,
                        $invitedBy ?? auth()->user(),
                    );
                }

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

            if (in_array($role, UserRole::studioRoles(), true)) {
                throw ValidationException::withMessages([
                    'email' => ['Çalışanlar önce uygulamadan uygun rolle kayıt olmalıdır.'],
                ]);
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

    public function acceptInvitation(StudioStaffInvitation $invitation, User $user): StudioStaffInvitation
    {
        return DB::transaction(function () use ($invitation, $user): StudioStaffInvitation {
            $invitation = StudioStaffInvitation::query()
                ->with('studio')
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            $this->ensureInvitationCanBeAnswered($invitation, $user);

            if ($user->studios()->wherePivot('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'invitation' => ['Başka bir stüdyoda aktif çalıştığınız için bu daveti kabul edemezsiniz.'],
                ]);
            }

            $role = UserRole::fromValue($invitation->role);
            $this->ensureStudioProfessionalSlotAvailable($invitation->studio, $role, $user->id);

            $membership = $invitation->studio->users()
                ->where('users.id', $user->id)
                ->first();

            if ($membership === null) {
                $invitation->studio->users()->attach($user->id, [
                    'role' => $role->value,
                    'work_status' => 'working',
                    'is_active' => true,
                    'joined_at' => now(),
                ]);
            } else {
                $invitation->studio->users()->updateExistingPivot($user->id, [
                    'role' => $role->value,
                    'work_status' => 'working',
                    'is_active' => true,
                    'joined_at' => now(),
                    'left_at' => null,
                ]);
            }

            $user->forceFill(['role' => $role])->save();

            $invitation->forceFill([
                'status' => 'accepted',
                'responded_at' => now(),
            ])->save();

            StudioStaffInvitation::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($invitation->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'responded_at' => now(),
                ]);

            $this->notifyInvitationResponse($invitation, true);

            return $invitation->fresh(['studio', 'invitedBy']);
        });
    }

    public function rejectInvitation(StudioStaffInvitation $invitation, User $user): StudioStaffInvitation
    {
        return DB::transaction(function () use ($invitation, $user): StudioStaffInvitation {
            $invitation = StudioStaffInvitation::query()
                ->with('studio')
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            $this->ensureInvitationCanBeAnswered($invitation, $user);

            $invitation->forceFill([
                'status' => 'rejected',
                'responded_at' => now(),
            ])->save();

            $this->notifyInvitationResponse($invitation, false);

            return $invitation->fresh(['studio', 'invitedBy']);
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
            $this->ensureStudioProfessionalSlotAvailable($studio, $newRole, $user->id);
            $pivotUpdates['role'] = $newRole->value;
            $user->role = $newRole;
            $user->save();
        }

        if (! empty($pivotUpdates ?? [])) {
            $studio->users()->updateExistingPivot($user->id, $pivotUpdates);
        }

        if (($attributes['is_active'] ?? null) === false) {
            $this->restoreRoleWhenInactive($user);
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

        $this->restoreRoleWhenInactive($user);
    }

    /**
     * @return array{user:User,studio_role:string,action:string,invitation:StudioStaffInvitation}
     */
    private function inviteRegisteredStaff(
        Studio $studio,
        UserRole $role,
        User $applicant,
        ?User $invitedBy
    ): array {
        if (! $applicant->hasStaffApplicationFor($role)) {
            throw ValidationException::withMessages([
                'role' => ['Kullanıcı uygulamada bu çalışma rolüyle kayıtlı değil.'],
            ]);
        }

        if ($invitedBy === null) {
            throw ValidationException::withMessages([
                'email' => ['Daveti gönderen kullanıcı bulunamadı.'],
            ]);
        }

        if ($applicant->studios()->wherePivot('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Bu kullanıcı zaten başka bir stüdyoda aktif çalışıyor.'],
            ]);
        }

        $invitation = StudioStaffInvitation::query()
            ->where('studio_id', $studio->id)
            ->where('user_id', $applicant->id)
            ->where('status', 'pending')
            ->first();

        if ($invitation === null) {
            $invitation = StudioStaffInvitation::query()->create([
                'studio_id' => $studio->id,
                'user_id' => $applicant->id,
                'invited_by_user_id' => $invitedBy->id,
                'role' => $role->value,
                'status' => 'pending',
            ]);
        }

        $this->fcmService->sendToUser(
            $applicant,
            'Yeni çalışanlık daveti',
            $studio->name.' sizi '.$role->label().' olarak ekibine davet etti.',
            'studio_staff_invitation',
            [
                'invitation_id' => (string) $invitation->id,
                'studio_id' => (string) $studio->id,
                'role' => $role->value,
            ],
        );

        return [
            'user' => $applicant,
            'studio_role' => $role->value,
            'action' => 'invited_existing_freelancer',
            'invitation' => $invitation,
        ];
    }

    private function ensureStudioProfessionalSlotAvailable(
        Studio $studio,
        UserRole $role,
        ?int $exceptUserId = null
    ): void {
        if (! in_array($role, [UserRole::Artist, UserRole::Designer], true)) {
            return;
        }

        $exists = $studio->users()
            ->wherePivot('is_active', true)
            ->wherePivot('role', $role->value)
            ->when($exceptUserId !== null, fn ($query) => $query->where('users.id', '!=', $exceptUserId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'role' => ["Bir stüdyoda sadece 1 {$role->label()} olabilir."],
            ]);
        }
    }

    private function restoreRoleWhenInactive(User $user): void
    {
        if ($user->studios()->wherePivot('is_active', true)->exists()) {
            return;
        }

        if (
            $user->requested_staff_role === null
            && in_array($user->role, [UserRole::Artist, UserRole::Designer], true)
        ) {
            $user->requested_staff_role = $user->role;
        }

        $user->role = $user->requested_staff_role === null
            ? UserRole::Kullanici
            : UserRole::KullaniciRol;
        $user->save();
    }

    private function ensureInvitationCanBeAnswered(StudioStaffInvitation $invitation, User $user): void
    {
        abort_unless((int) $invitation->user_id === (int) $user->id, 403);

        if ($invitation->status !== 'pending') {
            throw ValidationException::withMessages([
                'invitation' => ['Bu davet daha önce yanıtlanmış.'],
            ]);
        }
    }

    private function notifyInvitationResponse(StudioStaffInvitation $invitation, bool $accepted): void
    {
        $invitedBy = $invitation->invitedBy()->first();
        if ($invitedBy === null) {
            return;
        }

        $this->fcmService->sendToUser(
            $invitedBy,
            $accepted ? 'Çalışanlık daveti kabul edildi' : 'Çalışanlık daveti reddedildi',
            $invitation->user->fullName().' '.$invitation->studio->name.' davetinizi '
                .($accepted ? 'kabul etti.' : 'reddetti.'),
            $accepted ? 'studio_staff_invitation_accepted' : 'studio_staff_invitation_rejected',
            [
                'invitation_id' => (string) $invitation->id,
                'studio_id' => (string) $invitation->studio_id,
                'user_id' => (string) $invitation->user_id,
            ],
        );
    }
}
