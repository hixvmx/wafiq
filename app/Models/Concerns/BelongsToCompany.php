<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Services\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * For every business model (clients, items, documents, payments…):
 * queries only see the current company's rows, and new rows get its company_id.
 *
 * @mixin Model
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = app(CurrentCompany::class)->id();

            // No company resolved: show nothing rather than everyone's data.
            $companyId === null
                ? $builder->whereRaw('1 = 0')
                : $builder->where($builder->qualifyColumn('company_id'), $companyId);
        });

        static::creating(function (Model $model) {
            if ($model->getAttribute('company_id') !== null) {
                return;
            }

            $companyId = app(CurrentCompany::class)->id()
                ?? throw new LogicException('Cannot create '.class_basename($model).' without a current company.');

            $model->setAttribute('company_id', $companyId);
        });
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
