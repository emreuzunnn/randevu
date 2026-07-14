<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'surname',
        'username',
        'email',
        'phone',
        'bio',
        'location',
        'availability_start',
        'availability_end',
        'portfolio',
        'profile_image',
        'rating',
        'experience_years',
        'specializations',
        'instagram',
        'whatsapp',
        'response_time_hours',
        'password',
        'role',
        'requested_staff_role',
        'can_open_multiple_studios',
        'banned_at',
        'ban_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'         => 'datetime',
            'password'                  => 'hashed',
            'role'                      => UserRole::class,
            'requested_staff_role'      => UserRole::class,
            'can_open_multiple_studios' => 'boolean',
            'banned_at'                 => 'datetime',
            'portfolio'                 => 'array',
            'specializations'           => 'array',
            'experience_years'   => 'integer',
            'response_time_hours' => 'integer',
        ];
    }

    /** Relative path → tam URL dönüştürür; eski tam URL verilerse olduğu gibi döner */
    protected function profileImage(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => $value
            ? (str_starts_with($value, 'http') ? $value : url('storage/' . $value))
            : null
        );
    }

    /**
     * Portfolio öğelerindeki görece image_path'leri tam URL'e çevirir.
     * JSON decode burada yapılır çünkü Attribute accessor raw DB değerini alır.
     */
    protected function portfolio(): Attribute
    {
        return Attribute::get(function (?string $value): ?array {
            if ($value === null) {
                return null;
            }
            $items = json_decode($value, true) ?? [];
            return array_map(function (array $item): array {
                if (! empty($item['image_path']) && ! str_starts_with($item['image_path'], 'http')) {
                    $item['image_path'] = url('storage/' . $item['image_path']);
                }
                return $item;
            }, $items);
        });
    }

    public function hasRole(UserRole|string $role): bool
    {
        return $this->role === UserRole::fromValue($role);
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->name, $this->surname])));
    }

    public function profileCode(): string
    {
        return 'TD-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function issueApiToken(): string
    {
        $plainToken = Str::random(80);
        $this->forceFill(['api_token' => hash('sha256', $plainToken)])->save();
        return $plainToken;
    }

    public function revokeApiToken(): void
    {
        $this->forceFill(['api_token' => null])->save();
    }

    /**
     * @param  array<int, UserRole|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Kullanıcı yönetiminde görünür ve yönetilebilir alt roller.
     *
     * @return array<int, UserRole>
     */
    public function manageableStaffRoles(): array
    {
        return match ($this->role) {
            UserRole::Admin => [
                UserRole::Admin,
                UserRole::Yonetici,
                UserRole::Supervisor,
                UserRole::Designer,
                UserRole::Artist,
                UserRole::Info,
                UserRole::Sofor,
                UserRole::Calisan,
            ],
            UserRole::Yonetici => [
                UserRole::Supervisor,
                UserRole::Designer,
                UserRole::Artist,
                UserRole::Info,
                UserRole::Sofor,
                UserRole::Calisan,
            ],
            UserRole::Supervisor => [
                UserRole::Designer,
                UserRole::Artist,
                UserRole::Info,
                UserRole::Sofor,
                UserRole::Calisan,
            ],
            default => [],
        };
    }

    public function canManageStaffRole(UserRole|string $role): bool
    {
        return in_array(UserRole::fromValue($role), $this->manageableStaffRoles(), true);
    }

    public function hasProfessionalAccountRole(): bool
    {
        return in_array($this->profileRole(), [UserRole::Artist, UserRole::Designer], true)
            || ($this->requested_staff_role === null && $this->hasRole(UserRole::KullaniciRol));
    }

    public function profileRole(): UserRole
    {
        return $this->requested_staff_role ?? $this->role;
    }

    public function hasStaffApplicationFor(UserRole $role): bool
    {
        return $this->requested_staff_role === $role
            || ($this->requested_staff_role === null && $this->hasRole($role))
            || (
                $this->requested_staff_role === null
                && $this->hasRole(UserRole::KullaniciRol)
                && in_array($role, [UserRole::Artist, UserRole::Designer], true)
            );
    }

    public function isIndependentProfessional(): bool
    {
        return $this->hasProfessionalAccountRole()
            && ! $this->studios()->wherePivot('is_active', true)->exists();
    }

    public function isIndependentProfessionalFor(UserRole $role): bool
    {
        return $this->isIndependentProfessional()
            && (
                $this->profileRole() === $role
                || ($this->requested_staff_role === null && $this->hasRole(UserRole::KullaniciRol))
            );
    }

    public function isAvailableForStaffInvitation(UserRole $role): bool
    {
        return in_array($role, UserRole::studioRoles(), true)
            && $this->hasStaffApplicationFor($role)
            && ! $this->studios()->wherePivot('is_active', true)->exists();
    }

    public function ownedStudios(): HasMany
    {
        return $this->hasMany(Studio::class, 'owner_user_id');
    }

    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    public function managedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'manager_user_id');
    }

    public function studios(): BelongsToMany
    {
        return $this->belongsToMany(Studio::class)
            ->withPivot(['role', 'work_status', 'commission_rate', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function belongsToStudio(Studio|int $studio): bool
    {
        return $this->studios()
            ->whereKey($studio instanceof Studio ? $studio->getKey() : $studio)
            ->exists();
    }

    public function belongsToActiveStudio(Studio|int $studio): bool
    {
        return $this->studios()
            ->whereKey($studio instanceof Studio ? $studio->getKey() : $studio)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * @param  array<int, UserRole|string>  $roles
     */
    public function hasStudioRole(Studio|int $studio, array $roles): bool
    {
        $studioId = $studio instanceof Studio ? $studio->getKey() : $studio;
        $roleValues = array_map(
            static fn (UserRole|string $role): string => UserRole::fromValue($role)->value,
            $roles,
        );

        return $this->studios()
            ->where('studios.id', $studioId)
            ->wherePivot('is_active', true)
            ->wherePivotIn('role', $roleValues)
            ->exists();
    }

    public function canManageStudio(Studio|int $studio): bool
    {
        if ($this->hasRole(UserRole::Admin)) {
            return true;
        }

        $studioModel = $studio instanceof Studio ? $studio : Studio::query()->find($studio);

        if ($studioModel === null) {
            return false;
        }

        if ($this->hasRole(UserRole::Yonetici)) {
            return $studioModel->company_id !== null
                && $this->managedCompanies()->whereKey($studioModel->company_id)->exists();
        }

        if ($this->hasRole(UserRole::Supervisor)
            && $this->hasStudioRole($studioModel, [UserRole::Supervisor])) {
            return true;
        }

        return false;
    }

    public function canManageStudioAppointments(Studio|int $studio): bool
    {
        if ($this->canManageStudio($studio)) {
            return true;
        }

        $studioModel = $studio instanceof Studio ? $studio : Studio::query()->find($studio);

        if ($studioModel === null) {
            return false;
        }

        // Supervisor, designer, info, sofor randevu yönetebilir
        if ($this->hasStudioRole($studioModel, [
            UserRole::Supervisor,
            UserRole::Designer,
            UserRole::Info,
            UserRole::Sofor,
            UserRole::Calisan,
        ])) {
            return true;
        }

        return false;
    }

    /** Sanatçıya randevu atama yetkisi (supervisor ve üstü) */
    public function canAssignArtist(Studio|int $studio): bool
    {
        if ($this->canManageStudio($studio)) {
            return true;
        }

        $studioModel = $studio instanceof Studio ? $studio : Studio::query()->find($studio);

        if ($studioModel === null) {
            return false;
        }

        return $this->hasStudioRole($studioModel, [UserRole::Supervisor]);
    }

    public function canAccessStudio(Studio|int $studio): bool
    {
        $studioModel = $studio instanceof Studio ? $studio : Studio::query()->find($studio);
        if ($studioModel === null) {
            return false;
        }

        return $this->canManageStudioAppointments($studio)
            || $this->hasStudioRole($studioModel, [UserRole::Artist])
            || $this->belongsToActiveStudio($studio);
    }

    /**
     * @return array<int, int>
     */
    public function accessibleStudioIds(): array
    {
        if ($this->hasRole(UserRole::Admin)) {
            return Studio::query()->pluck('id')->all();
        }

        if ($this->hasRole(UserRole::Yonetici)) {
            $companyIds = $this->managedCompanies()->pluck('id');
            return Studio::query()
                ->whereIn('company_id', $companyIds)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $studioIds = $this->studios()
            ->wherePivot('is_active', true)
            ->pluck('studios.id')
            ->all();

        return array_values(array_unique(array_map('intval', $studioIds)));
    }

    /**
     * Personel yönetimi kapsamı: admin tüm proje, yönetici şirket, supervisor atandığı stüdyo.
     *
     * @return array<int, int>
     */
    public function staffScopeStudioIds(): array
    {
        if ($this->hasRole(UserRole::Admin)) {
            return Studio::query()->pluck('id')->map('intval')->all();
        }

        if ($this->hasRole(UserRole::Yonetici)) {
            $companyIds = $this->managedCompanies()->pluck('id');
            $studioIds = Studio::query()
                ->whereIn('company_id', $companyIds)
                ->pluck('id');

            if ($studioIds->isNotEmpty()) {
                return $studioIds->unique()->map('intval')->values()->all();
            }
        }

        if ($this->hasRole(UserRole::Supervisor)) {
            return $this->studios()
                ->wherePivot('role', UserRole::Supervisor->value)
                ->wherePivot('is_active', true)
                ->pluck('studios.id')
                ->map('intval')
                ->all();
        }

        return $this->accessibleStudioIds();
    }

    /** Kullanıcının stüdyoda artist olup olmadığını kontrol eder */
    public function isStudioArtist(Studio|int $studio): bool
    {
        return $this->hasStudioRole($studio, [UserRole::Artist]);
    }
}
