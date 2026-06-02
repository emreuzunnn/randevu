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

    public function ownedStudios(): HasMany
    {
        return $this->hasMany(Studio::class, 'owner_user_id');
    }

    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    public function managedShops(): HasMany
    {
        return $this->hasMany(Shop::class, 'manager_user_id');
    }

    public function managedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'manager_user_id');
    }

    public function supervisedShops(): HasMany
    {
        return $this->hasMany(Shop::class, 'supervisor_user_id');
    }

    public function studios(): BelongsToMany
    {
        return $this->belongsToMany(Studio::class)
            ->withPivot(['role', 'work_status', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function belongsToStudio(Studio|int $studio): bool
    {
        return $this->studios()
            ->whereKey($studio instanceof Studio ? $studio->getKey() : $studio)
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

    public function canManageShop(Shop|int $shop): bool
    {
        if ($this->hasRole(UserRole::Admin)) {
            return true;
        }

        $shopModel = $shop instanceof Shop ? $shop : Shop::query()->find($shop);

        if ($shopModel === null) {
            return false;
        }

        $shopId = $shopModel->getKey();

        if ($this->hasRole(UserRole::Yonetici)) {
            return (
                $shopModel->company_id !== null
                && $this->managedCompanies()->whereKey($shopModel->company_id)->exists()
            )
                || $this->managedShops()->whereKey($shopId)->exists();
        }

        return $this->hasRole(UserRole::Supervisor)
            && (
                $this->supervisedShops()->whereKey($shopId)->exists()
                || $this->managedShops()->whereKey($shopId)->exists()
            );
    }

    public function canManageStudiosInShop(Shop|int $shop): bool
    {
        return $this->canManageShop($shop);
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

        if (
            $this->hasAnyRole([UserRole::Yonetici, UserRole::Supervisor])
            && in_array($studioModel->id, $this->staffScopeStudioIds(), true)
        ) {
            return true;
        }

        // Platform yöneticisi ve şube supervisor'ı
        if ($this->hasStudioRole($studioModel, [UserRole::Admin, UserRole::Yonetici, UserRole::Supervisor])) {
            return true;
        }

        return $studioModel->shop_id !== null
            && $this->canManageStudiosInShop($studioModel->shop_id);
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

        // Shop supervisor
        if (! $this->hasRole(UserRole::Supervisor)) {
            return false;
        }

        return $studioModel->shop_id !== null
            && $this->canManageShop($studioModel->shop_id);
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
            || $this->belongsToStudio($studio);
    }

    /**
     * @return array<int, int>
     */
    public function accessibleStudioIds(): array
    {
        if ($this->hasRole(UserRole::Admin)) {
            return Studio::query()->pluck('id')->all();
        }

        $studioIds = $this->studios()->pluck('studios.id')->all();

        if ($this->hasRole(UserRole::Yonetici)) {
            $companyIds = $this->managedCompanies()->pluck('id')
                ->merge($this->managedShops()->whereNotNull('company_id')->pluck('company_id'))
                ->unique();
            $studioIds = array_merge(
                $studioIds,
                Studio::query()
                    ->whereHas('shop', fn ($query) => $query->whereIn('company_id', $companyIds))
                    ->pluck('id')
                    ->all(),
                Studio::query()
                    ->whereIn('shop_id', $this->managedShops()->pluck('id'))
                    ->pluck('id')
                    ->all(),
            );
        }

        if ($this->hasRole(UserRole::Supervisor)) {
            $shopIds = $this->supervisedShops()->pluck('id')
                ->merge($this->managedShops()->pluck('id'))
                ->unique();
            $studioIds = array_merge(
                $studioIds,
                Studio::query()->whereIn('shop_id', $shopIds)->pluck('id')->all(),
            );
        }

        return array_values(array_unique(array_map('intval', $studioIds)));
    }

    /**
     * Personel yönetimi kapsamı: admin tüm proje, yönetici şirket, supervisor şube.
     *
     * @return array<int, int>
     */
    public function staffScopeStudioIds(): array
    {
        if ($this->hasRole(UserRole::Admin)) {
            return Studio::query()->pluck('id')->map('intval')->all();
        }

        if ($this->hasRole(UserRole::Yonetici)) {
            $companyIds = $this->managedCompanies()->pluck('id')
                ->merge($this->managedShops()->whereNotNull('company_id')->pluck('company_id'))
                ->unique();
            $studioIds = Studio::query()
                ->whereIn('shop_id', $this->managedShops()->pluck('id'))
                ->pluck('id');

            if ($companyIds->isNotEmpty()) {
                $studioIds = $studioIds->merge(Studio::query()
                    ->whereHas('shop', fn ($query) => $query->whereIn('company_id', $companyIds))
                    ->pluck('id')
                );
            }

            if ($studioIds->isNotEmpty()) {
                return $studioIds->unique()->map('intval')->values()->all();
            }
        }

        if ($this->hasRole(UserRole::Supervisor)) {
            $shopIds = $this->supervisedShops()->pluck('id')
                ->merge($this->managedShops()->pluck('id'))
                ->unique();

            if ($shopIds->isEmpty()) {
                $shopIds = $this->studios()
                    ->wherePivot('role', UserRole::Supervisor->value)
                    ->wherePivot('is_active', true)
                    ->pluck('studios.shop_id')
                    ->filter();
            }

            if ($shopIds->isNotEmpty()) {
                return Studio::query()
                    ->whereIn('shop_id', $shopIds)
                    ->pluck('id')
                    ->map('intval')
                    ->all();
            }
        }

        return $this->accessibleStudioIds();
    }

    /** Kullanıcının stüdyoda artist olup olmadığını kontrol eder */
    public function isStudioArtist(Studio|int $studio): bool
    {
        return $this->hasStudioRole($studio, [UserRole::Artist]);
    }
}
