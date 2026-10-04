<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'vat_number',
        'cr_number',
        'address',
        'phone',
        'email',
        'logo',
        'stamp',
        'signature',
        'currency',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function addMember(User $user, Role $role): void
    {
        $this->users()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
    }

    /** Folder for this company's uploads, e.g. "companies/3/logos". */
    public function storagePath(string $folder = ''): string
    {
        return trim("companies/{$this->id}/{$folder}", '/');
    }
}
