<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safe defaults when the app is reached from outside this computer (phone on Wi-Fi, ngrok):
 * no debug error pages, secure-only cookies over HTTPS, and standard browser security headers.
 */
class SecureHeaders
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]'];

    public function handle(Request $request, Closure $next): Response
    {
        // Debug pages reveal code and settings; only show them to someone on this computer.
        if (! in_array($request->getHost(), self::LOCAL_HOSTS, true)) {
            config(['app.debug' => false]);
        }

        // Over HTTPS (e.g. ngrok), the session cookie is never sent over plain HTTP.
        if ($request->isSecure()) {
            config(['session.secure' => true]);
        }

        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
