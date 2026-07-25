<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return $default;
            }

            $setting = self::query()->where('key', $key)->first();
        } catch (Throwable) {
            return $default;
        }

        $value = $setting?->value;

        return is_array($value) && array_key_exists('value', $value)
            ? $value['value']
            : $default;
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        return (bool) self::valueFor($key, $default);
    }

    public static function setValue(string $key, mixed $value): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => $value]],
        );
    }
}
