<?php

namespace App\Http\Middleware;

use App\Support\Edition;
use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Until the buyer has run the installer, every page leads to it; afterwards the installer is gone. */
class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Edition::isSaas()) {
            return $next($request);
        }

        $installing = $request->is('install', 'install/*');

        if (! $installing && ! Installer::isInstalled()) {
            return redirect('/install');
        }

        if ($installing && Installer::isInstalled() && ! $request->session()->get('install.finishing')) {
            abort(404);
        }

        return $next($request);
    }
}
