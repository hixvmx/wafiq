<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser protections for every page.
 *
 * - The client's approval page must never be framed by another site (clickjacking a "موافقة").
 * - The tracked link /d/{token} is the key to a document: it must not leak to other sites in
 *   the Referer header (Referrer-Policy: same-origin).
 * - On HTTPS the session cookie is marked Secure, and browsers are told to stay on HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before the session cookie is created (this runs ahead of StartSession).
        if ($request->isSecure() && config('session.secure') === null) {
            config(['session.secure' => true]);
        }

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            // No includeSubDomains: the buyer's other subdomains may not all have HTTPS.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
