<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\CompanySettings;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /** Branding images: logo is public (client page, emails); stamp and signature are for members and PDFs. */
    public const IMAGES = ['logo', 'stamp', 'signature'];

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

    /** Settings with defaults (numbering, templates, document defaults…). */
    public function preferences(): CompanySettings
    {
        return new CompanySettings($this);
    }

    /** Folder for this company's uploads, e.g. "companies/3/branding". */
    public function storagePath(string $folder = ''): string
    {
        return trim("companies/{$this->id}/{$folder}", '/');
    }

    /** URL of a branding image (the file name busts browser caches after a change). */
    public function imageUrl(string $kind): ?string
    {
        $path = $this->getAttribute($kind);

        return $path ? route('branding.show', ['company' => $this->id, 'kind' => $kind, 'v' => pathinfo($path, PATHINFO_FILENAME)]) : null;
    }
}
