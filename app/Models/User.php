<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'surname',
        'email',
        'phone',
        'profile_image',
        'rating',
        'password',
        'role',
        'can_open_multiple_studios',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'can_open_multiple_studios' => 'boolean',
        ];
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

        $this->forceFill([
            'api_token' => hash('sha256', $plainToken),
        ])->save();

        return $plainToken;
    }

    public function revokeApiToken(): void
    {
        $this->forceFill([
            'api_token' => null,
        ])->save();
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
            ->withPivot([
                'role',
                'work_status',
                'is_active',
                'joined_at',
                'left_at',
            ])
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

        if ($this->hasStudioRole($studioModel, [UserRole::Admin, UserRole::Yonetici])) {
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

        if ($this->hasStudioRole($studioModel, [UserRole::Supervisor])) {
            return true;
        }

        if (! $this->hasRole(UserRole::Supervisor)) {
            return false;
        }

        return $studioModel->shop_id !== null
            && $this->managedShops()
                ->whereKey($studioModel->shop_id)
                ->where('is_active', true)
                ->exists();
    }

    public function canAccessStudio(Studio|int $studio): bool
    {
        return $this->canManageStudioAppointments($studio) || $this->belongsToStudio($studio);
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
}
