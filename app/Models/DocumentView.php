<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** One real view of a tracked link by the client (bots and team members excluded). */
class DocumentView extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }
}
