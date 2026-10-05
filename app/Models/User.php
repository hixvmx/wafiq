<?php

namespace App\Models;

use App\Enums\Role;
use App\Services\CurrentCompany;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<int, Role|null> memoised roles, see currentRole() */
    private array $rolesByCompany = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'job_title',
        'phone',
        'email',
        'password',
        'avatar',
        'notification_prefs',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'last_login_at' => 'datetime',
            'notification_prefs' => 'array',
            'password' => 'hashed',
        ];
    }

    /**
     * Companies this user is a member of (exactly one in the self-hosted edition).
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role')->withTimestamps();
    }

    /** Profile picture URL (the file name changes on every upload, so it can be cached forever). */
    public function avatarUrl(): ?string
    {
        return $this->avatar ? route('avatars.show', ['user' => $this->id, 'v' => pathinfo($this->avatar, PATHINFO_FILENAME)]) : null;
    }

    /** @return HasMany<LoginToken, $this> */
    public function loginTokens(): HasMany
    {
        return $this->hasMany(LoginToken::class);
    }

    /** The user's role in a company, or null when not a member. */
    public function roleIn(?Company $company): ?Role
    {
        if (! $company) {
            return null;
        }

        $role = $this->companies()->whereKey($company->id)->first()?->pivot->role;

        return $role ? Role::tryFrom($role) : null;
    }

    /** The user's role in the current company (memoised: permission checks call it often). */
    public function currentRole(): ?Role
    {
        $company = app(CurrentCompany::class)->get();

        if (! $company) {
            return null;
        }

        if (! array_key_exists($company->id, $this->rolesByCompany)) {
            $this->rolesByCompany[$company->id] = $this->roleIn($company);
        }

        return $this->rolesByCompany[$company->id];
    }

    /** Forget memoised roles after a role change. */
    public function refreshRoles(): void
    {
        $this->rolesByCompany = [];
    }
}
