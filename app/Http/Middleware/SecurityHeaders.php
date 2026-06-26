<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Attach baseline security headers to every web response.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
        ];

        // HSTS only matters over TLS; emitting it on plain-HTTP local dev would
        // pin browsers to an https:// origin that Herd may not serve.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Build the Content-Security-Policy.
     *
     * The framing/object protections (object-src, base-uri, frame-ancestors)
     * apply in every environment. The asset-fetch directives are only emitted
     * for built deployments: while the Vite dev server is running it serves
     * assets from an origin (often an IPv6 loopback like http://[::1]:5173)
     * that CSP host-source syntax cannot express, so locking fetch directives
     * down in dev would only break HMR. 'unsafe-inline' stays on script/style
     * because the root Blade template ships an inline theme-detection script
     * and inline background style; tighten to nonces to drop it later.
     */
    private function contentSecurityPolicy(): string
    {
        $directives = [
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
        ];

        if (! Vite::isRunningHot()) {
            array_unshift(
                $directives,
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:",
                "font-src 'self' data:",
                "connect-src 'self'",
            );
        }

        return implode('; ', $directives);
    }
}
