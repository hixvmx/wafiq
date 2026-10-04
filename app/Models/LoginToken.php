<?php

namespace App\Models;

use App\Support\Token;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginToken extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'used_at', 'ip'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The unused, unexpired token matching a plain token from a link. */
    public static function findUsable(string $token): ?self
    {
        return static::query()
            ->where('token_hash', Token::hash($token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /** @param Builder<self> $query */
    public function scopeUnused(Builder $query): void
    {
        $query->whereNull('used_at');
    }
}
