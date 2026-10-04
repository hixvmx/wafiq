<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A quotation or an invoice (one revision of it). */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToCompany, HasFactory;

    public const TYPES = ['quote', 'invoice'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'is_latest' => 'boolean',
            'client_snapshot' => 'array',
            'company_snapshot' => 'array',
            'issue_date' => 'date',
            'valid_until' => 'date',
            'due_date' => 'date',
            'discount_value' => 'string',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'sent_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /** @return HasMany<DocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class)->orderBy('position');
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The quotation an invoice was made from. @return BelongsTo<Document, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'quote_id');
    }

    /** The invoice made from this quotation. @return HasOne<Document, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(self::class, 'quote_id')->where('is_latest', true);
    }

    /** Every revision of this document, oldest first. @return HasMany<Document, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'root_id', 'root_id')->orderBy('revision');
    }

    /** @param Builder<self> $query */
    public function scopeLatestRevisions(Builder $query): void
    {
        $query->where('is_latest', true);
    }

    /** @param Builder<self> $query */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /** "QT-2026-0042", or "QT-2026-0042-v2" from the second revision on. */
    public function displayNumber(): string
    {
        return $this->revision > 1 ? "{$this->number}-v{$this->revision}" : $this->number;
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /** URL segment for this type: "quotes" / "invoices". */
    public function routePrefix(): string
    {
        return self::prefixFor($this->type);
    }

    public static function prefixFor(string $type): string
    {
        return $type === 'invoice' ? 'invoices' : 'quotes';
    }
}
