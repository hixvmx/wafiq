<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TaxRate extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'rate', 'is_default'];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'is_default' => 'boolean',
        ];
    }

    /** Only one rate is the default for new lines. */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            static::whereKeyNot($this->id)->update(['is_default' => false]);
            $this->update(['is_default' => true]);
        });
    }
}
