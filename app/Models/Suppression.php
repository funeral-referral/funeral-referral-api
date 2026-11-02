<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['email', 'reason', 'funeral_home_id'])]
class Suppression extends Model
{
    public static function isSuppressed(string $email): bool
    {
        return static::query()->where('email', static::normalize($email))->exists();
    }

    /**
     * Never email this address again. Safe to call twice.
     */
    public static function add(string $email, string $reason, ?int $funeralHomeId = null): self
    {
        return static::query()->firstOrCreate(
            ['email' => static::normalize($email)],
            ['reason' => $reason, 'funeral_home_id' => $funeralHomeId],
        );
    }

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
