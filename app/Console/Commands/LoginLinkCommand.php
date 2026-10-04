<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MagicLink;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Rescue for broken email settings: prints a login link instead of emailing it. */
class LoginLinkCommand extends Command
{
    protected $signature = 'wafiq:login-link {email : The member\'s email address}';

    protected $description = 'Print a one-time login link for a team member (when email is not working)';

    public function handle(MagicLink $links): int
    {
        $user = User::where('email', Str::lower(trim($this->argument('email'))))->first();

        if (! $user || ! $links->canSignIn($user)) {
            $this->error('No team member with this email.');

            return self::FAILURE;
        }

        $this->info("Login link for {$user->name} (valid ".config('wafiq.login_link_minutes').' minutes, single use):');
        $this->line($links->create($user));

        return self::SUCCESS;
    }
}
