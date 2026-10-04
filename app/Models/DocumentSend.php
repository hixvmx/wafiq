<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Token;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One tracked link: a document sent once, by one channel, to one recipient. */
class DocumentSend extends Model
{
    use BelongsToCompany;

    public const CHANNELS = ['email', 'whatsapp', 'link'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** @return HasMany<DocumentView, $this> */
    public function views(): HasMany
    {
        return $this->hasMany(DocumentView::class);
    }

    /**
     * The send behind a public link, in any company: the client opening it is a guest.
     * Revoked links don't resolve.
     */
    public static function findByToken(string $token): ?self
    {
        return static::withoutGlobalScope('company')
            ->where('token_hash', Token::hash($token))
            ->whereNull('revoked_at')
            ->first();
    }
}
