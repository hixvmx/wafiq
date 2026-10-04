<?php

namespace App\Providers;

use App\Enums\Ability;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request (and reset between queued jobs).
        $this->app->scoped(CurrentCompany::class);
        $this->app->alias(CurrentCompany::class, 'company.current'); // short name for Blade views
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // $user->can('manage_team'), @can, $this->authorize()… based on the role in the current company.
        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn (User $user) => (bool) $user->currentRole()?->allows($ability));
        }
    }
}
