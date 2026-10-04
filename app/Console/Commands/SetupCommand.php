<?php

namespace App\Console\Commands;

use App\Actions\CreateCompany;
use App\Models\Company;
use App\Services\CurrentCompany;
use App\Services\MagicLink;
use App\Support\Edition;
use App\Support\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates the company and its owner from the command line: for servers where the web
 * installer can't run (and for development). Same steps as the installer's last screen.
 */
class SetupCommand extends Command
{
    protected $signature = 'wafiq:setup
        {--company= : Company name}
        {--name= : Owner\'s name}
        {--email= : Owner\'s email}';

    protected $description = 'Create the company and its owner, then print a login link';

    public function handle(CurrentCompany $current, MagicLink $links): int
    {
        if (Edition::isSelfHosted() && Company::exists()) {
            $this->error('Wafiq is already set up: this install has a company.');

            return self::FAILURE;
        }

        $data = [
            'company' => $this->option('company') ?? $this->ask('Company name'),
            'name' => $this->option('name') ?? $this->ask('Owner name'),
            'email' => Str::lower(trim((string) ($this->option('email') ?? $this->ask('Owner email')))),
        ];

        $validator = Validator::make($data, [
            'company' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        [$company, $owner] = app(CreateCompany::class)->handle($data);
        $current->set($company);
        Installer::markInstalled();

        $this->info("Company \"{$company->name}\" created with owner {$owner->email}.");
        $this->line('First login link (valid '.config('wafiq.login_link_minutes').' minutes):');
        $this->line($links->create($owner));

        return self::SUCCESS;
    }
}
