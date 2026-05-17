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
        'can_open_multiple_studios',
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
            'can_open_multiple_studios' => 'boolean',
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

    public function ownedStudios(): HasMany
    {
        return $this->hasMany(Studio::class, 'owner_user_id');
    }

    public function managedShops(): HasMany
    {
        return $this->hasMany(Shop::class, 'manager_user_id');
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

        $shopId = $shop instanceof Shop ? $shop->getKey() : $shop;

        if (! $this->hasRole(UserRole::Yonetici)) {
            return false;
        }

        return $this->managedShops()
            ->whereKey($shopId)
            ->where('is_active', true)
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

        // Platform yöneticisi ve şube supervisor'ı
        if ($this->hasStudioRole($studioModel, [UserRole::Admin, UserRole::Yonetici, UserRole::Supervisor])) {
            return true;
        }

        return $studioModel->shop_id !== null
            && $this->canManageShop($studioModel->shop_id);
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
            && $this->managedShops()
                ->whereKey($studioModel->shop_id)
                ->where('is_active', true)
                ->exists();
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

        if ($this->hasAnyRole([UserRole::Yonetici, UserRole::Supervisor])) {
            $managedShopIds = $this->managedShops()->pluck('id');
            if ($managedShopIds->isNotEmpty()) {
                $studioIds = array_merge(
                    $studioIds,
                    Studio::query()->whereIn('shop_id', $managedShopIds)->pluck('id')->all(),
                );
            }
        }

        return array_values(array_unique(array_map('intval', $studioIds)));
    }

    /** Kullanıcının stüdyoda artist olup olmadığını kontrol eder */
    public function isStudioArtist(Studio|int $studio): bool
    {
        return $this->hasStudioRole($studio, [UserRole::Artist]);
    }
}
