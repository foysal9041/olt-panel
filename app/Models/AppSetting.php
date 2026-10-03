<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Small settings kept as key → JSON value (e.g. "hr" for ID cards and letters). */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /** The stored value merged over the defaults. */
    public static function get(string $key, array $defaults = []): array
    {
        return array_merge($defaults, static::find($key)?->value ?? []);
    }

    public static function put(string $key, array $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
