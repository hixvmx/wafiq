<?php

namespace App\Http\Middleware;

use App\Enums\Ability;
use App\Http\Controllers\NotificationController;
use App\Services\CurrentCompany;
use App\Support\Edition;
use App\Support\Installer;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Props available to every page.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'locale' => app()->getLocale(),
                'edition' => Edition::name(),
            ],
            'company' => function () {
                // Before installation there may be no database to ask.
                $company = Edition::isSaas() || Installer::isInstalled() ? app(CurrentCompany::class)->get() : null;

                return $company ? [...$company->only('id', 'name', 'currency'), 'logo_url' => $company->imageUrl('logo')] : null;
            },
            'auth' => [
                'user' => $user ? [...$user->only('id', 'name', 'email', 'job_title'), 'avatar' => $user->avatarUrl()] : null,
                'role' => fn () => $user?->currentRole()?->value,
                'unread_notifications' => fn () => $user && app(CurrentCompany::class)->id()
                    ? NotificationController::query($request)->whereNull('read_at')->count()
                    : 0,
                // e.g. auth.can.manage_team, to show or hide menu items and buttons.
                'can' => fn () => $user
                    ? collect(Ability::cases())->mapWithKeys(fn (Ability $ability) => [$ability->value => $user->can($ability->value)])
                    : (object) [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // UI strings (lang/ar/ui.php), used by the useT() hook in React.
            'translations' => fn () => trans('ui'),
        ];
    }
}
