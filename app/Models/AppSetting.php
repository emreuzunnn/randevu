<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        $setting = self::query()->where('key', $key)->first();
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
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => $value]],
        );
    }
}
