<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Money;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A product or service from the catalog, picked when filling document lines. */
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const TYPES = ['product', 'service'];

    protected $fillable = ['type', 'name', 'description', 'unit', 'price_minor', 'currency', 'tax_rate_id'];

    protected function casts(): array
    {
        return ['price_minor' => 'integer'];
    }

    /** @return BelongsTo<TaxRate, $this> */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like));
    }

    /** @return array<string, mixed> */
    public function toFormArray(): array
    {
        return [
            ...$this->only('id', 'type', 'name', 'description', 'unit', 'currency', 'tax_rate_id'),
            'price' => Money::fromMinor($this->price_minor, $this->currency),
        ];
    }
}
