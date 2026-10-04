<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Digits;
use App\Support\Phone;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const TYPES = ['company', 'person'];

    protected $fillable = ['type', 'name', 'contact_name', 'email', 'phone', 'vat_number', 'cr_number', 'address', 'owner_id'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Matches name, contact, email or phone. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $digits = preg_replace('/\D/', '', Digits::latin($term));

        $query->where(function (Builder $q) use ($like, $digits) {
            $q->where('name', 'like', $like)
                ->orWhere('contact_name', 'like', $like)
                ->orWhere('email', 'like', $like);

            if (strlen($digits) >= 3) {
                $q->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%');
            }
        });
    }

    /** @return array<string, mixed> */
    public function toFormArray(): array
    {
        [$code, $number] = Phone::split($this->phone);

        return [
            ...$this->only('id', 'type', 'name', 'contact_name', 'email', 'phone', 'vat_number', 'cr_number', 'address'),
            'phone_code' => $code,
            'phone_number' => $number,
        ];
    }
}
