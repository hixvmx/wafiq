<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Token;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'email', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Give the invitation a fresh token and expiry; returns the plain token for the email. */
    public function renew(): string
    {
        $token = Token::generate();

        $this->forceFill([
            'token_hash' => Token::hash($token),
            'expires_at' => now()->addDays(config('wafiq.invitation_days')),
        ])->save();

        return $token;
    }

    /**
     * The pending, unexpired invitation behind a link, in any company:
     * the guest who opens it has no current company yet.
     */
    public static function findUsable(string $token): ?self
    {
        return static::withoutGlobalScope('company')
            ->where('token_hash', Token::hash($token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
