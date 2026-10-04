<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a document. Its company is the document's company. */
class DocumentLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'string',
            'discount_percent' => 'string',
            'tax_rate' => 'string',
            'unit_price_minor' => 'integer',
            'gross_minor' => 'integer',
            'discount_minor' => 'integer',
            'net_minor' => 'integer',
            'discount_share_minor' => 'integer',
            'tax_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** Fields copied when duplicating, revising or converting. */
    public function copyable(): array
    {
        return $this->only(
            'item_id', 'position', 'name', 'description', 'qty', 'unit', 'unit_price_minor', 'discount_percent',
            'tax_rate_id', 'tax_name', 'tax_rate', 'gross_minor', 'discount_minor', 'net_minor', 'discount_share_minor', 'tax_minor',
        );
    }
}
