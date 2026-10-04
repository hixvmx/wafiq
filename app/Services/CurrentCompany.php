<?php

namespace App\Services;

use App\Models\Company;
use App\Support\Edition;

/**
 * The company the current request works for. Every company-owned model is
 * scoped to it (see BelongsToCompany).
 *
 * - Self-hosted edition: always the single company created by the installer.
 * - SaaS edition: set by middleware from the subdomain / user membership.
 *   Until it is set, company-owned queries return nothing (fail closed).
 */
class CurrentCompany
{
    private ?Company $company = null;

    private bool $resolved = false;

    public function get(): ?Company
    {
        if (! $this->resolved) {
            $this->resolved = true;

            if (Edition::isSelfHosted()) {
                $this->company = Company::query()->orderBy('id')->first();
            }
        }

        return $this->company;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function set(?Company $company): void
    {
        $this->company = $company;
        $this->resolved = true;
    }

    /** Forget the cached company (e.g. right after the installer creates it). */
    public function forget(): void
    {
        $this->company = null;
        $this->resolved = false;
    }
}
