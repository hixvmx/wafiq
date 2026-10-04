<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Records an entry on the document's timeline. The company comes from the document,
     * so this also works where no company is "current" (the hourly expiry job).
     */
    public static function log(Document $document, ActivityType $type, array $data = [], ?User $user = null): self
    {
        $activity = new static;
        $activity->forceFill([
            'company_id' => $document->company_id,
            'document_id' => $document->id,
            'user_id' => $user?->id,
            'type' => $type,
            'data' => $data ?: null,
        ])->save();

        return $activity;
    }
}
