<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Company;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** The company and its owner: the end of installation (web installer or `wafiq:setup`). */
class CreateCompany
{
    /** Saudi VAT preset added when the company uses SAR (it can be changed in Settings). */
    private const SAR_VAT = ['name' => 'ضريبة القيمة المضافة', 'rate' => 15];

    /**
     * @param  array{company: string, name: string, email: string, currency?: string}  $data
     * @return array{Company, User}
     */
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $email = Str::lower(trim($data['email']));
            $currency = $data['currency'] ?? 'SAR';

            $company = Company::create([
                'name' => trim($data['company']),
                'email' => $email,
                'currency' => $currency,
                'settings' => ['currencies' => [$currency]],
            ]);
            $owner = User::firstOrCreate(['email' => $email], ['name' => trim($data['name'])]);
            $company->addMember($owner, Role::Owner);

            app(CurrentCompany::class)->set($company);
            if ($currency === 'SAR') {
                TaxRate::create([...self::SAR_VAT, 'is_default' => true]);
            }

            return [$company, $owner];
        });
    }
}
