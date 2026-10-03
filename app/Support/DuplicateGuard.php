<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Catches the same entry being saved twice — a double click, a re-sent
 * form, two people typing the same slip — by looking for an identical row
 * saved a moment ago.
 */
class DuplicateGuard
{
    public const SECONDS = 120;

    /** Was a row matching $query saved in the last couple of minutes? */
    public static function recent(Builder $query, int $seconds = self::SECONDS): bool
    {
        return $query->where($query->getModel()->getTable() . '.created_at', '>=', now()->subSeconds($seconds))->exists();
    }

    public static function message(): string
    {
        return 'The same entry was saved a moment ago — it was not added again. If it really is a second one, change the note or wait two minutes.';
    }
}
