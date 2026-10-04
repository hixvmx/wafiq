<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    use BelongsToCompany;

    protected $fillable = ['type', 'year', 'next_number'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'next_number' => 'integer',
        ];
    }
}
