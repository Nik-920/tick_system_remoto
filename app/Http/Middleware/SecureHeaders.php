<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->setIfFilled($response, 'X-Frame-Options', (string) config('security_headers.frame_options', 'SAMEORIGIN'));
        $this->setIfFilled($response, 'X-Content-Type-Options', (string) config('security_headers.content_type_options', 'nosniff'));
        $this->setIfFilled($response, 'Referrer-Policy', (string) config('security_headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $this->setIfFilled($response, 'Permissions-Policy', (string) config('security_headers.permissions_policy', 'camera=(), geolocation=(), microphone=()'));
        $this->setIfFilled($response, 'Cross-Origin-Opener-Policy', (string) config('security_headers.cross_origin_opener_policy', 'same-origin'));
        $this->setIfFilled($response, 'Cross-Origin-Resource-Policy', (string) config('security_headers.cross_origin_resource_policy', 'same-origin'));

        $this->applyContentSecurityPolicy($response);
        $this->applyHsts($request, $response);

        return $response;
    }

    private function applyContentSecurityPolicy(Response $response): void
    {
        $policy = trim((string) config('security_headers.csp', ''));
        if ($policy === '') {
            return;
        }

        $mode = strtolower(trim((string) config('security_headers.csp_mode', 'report-only')));

        if ($mode === 'enforce') {
            $response->headers->remove('Content-Security-Policy-Report-Only');
            $response->headers->set('Content-Security-Policy', $policy);

            return;
        }

        $response->headers->remove('Content-Security-Policy');
        $response->headers->set('Content-Security-Policy-Report-Only', $policy);
    }

    private function applyHsts(Request $request, Response $response): void
    {
        if (! (bool) config('security_headers.hsts_enabled', true)) {
            return;
        }

        $forwardedProto = strtolower(trim((string) $request->headers->get('X-Forwarded-Proto', '')));
        $isSecureRequest = $request->isSecure() || $forwardedProto === 'https' || (bool) config('security_headers.hsts_force', false);

        if (! $isSecureRequest) {
            return;
        }

        $maxAge = (int) config('security_headers.hsts_max_age', 31536000);
        $policy = 'max-age='.max($maxAge, 0);

        if ((bool) config('security_headers.hsts_include_subdomains', true)) {
            $policy .= '; includeSubDomains';
        }

        if ((bool) config('security_headers.hsts_preload', false)) {
            $policy .= '; preload';
        }

        $response->headers->set('Strict-Transport-Security', $policy);
    }

    private function setIfFilled(Response $response, string $header, string $value): void
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return;
        }

        $response->headers->set($header, $trimmed);
    }
}
