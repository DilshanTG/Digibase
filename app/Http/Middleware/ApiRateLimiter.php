<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiter
{
    /**
     * Handle an incoming request with dynamic rate limiting based on API key.
     *
     * Bypass: Authenticated Filament admin users (session auth) and requests
     * with the X-Digibase-Internal header are never rate-limited.
     * The admin must be a god.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ── GOD MODE: bypass rate limit for admin panel users ──
        if ($this->isAdminOrInternal($request)) {
            $response = $next($request);

            return $response->withHeaders([
                'X-RateLimit-Limit' => 'unlimited',
                'X-RateLimit-Remaining' => 'unlimited',
            ]);
        }

        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey) {
            return $this->applyDefaultRateLimit($request, $next);
        }

        // Get rate limit from API key (default to 60 if not set)
        $maxAttempts = $apiKey->rate_limit ?? 60;
        $decayMinutes = 1;

        // Create unique key for this API key + IP combination
        // This prevents one client IP from exhausting the limit for the entire key,
        // and also prevents a distributed attack from bypassing IP-based limits if they were using that.
        $key = 'api:'.$apiKey->id.':'.$request->ip();

        // Check rate limit
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many requests. Please slow down.',
                'retry_after' => $seconds,
                'limit' => $maxAttempts,
                'remaining' => 0,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($seconds)->timestamp,
                'Retry-After' => $seconds,
            ]);
        }

        // Hit the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        $remaining = $maxAttempts - RateLimiter::attempts($key);

        $response = $next($request);

        // Add rate limit headers to response
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addMinutes($decayMinutes)->timestamp,
        ]);
    }

    /**
     * Check if the request comes from a logged-in Filament admin (session auth)
     * or carries the internal bypass flag.
     */
    protected function isAdminOrInternal(Request $request): bool
    {
        // 1. Session-authenticated Filament admin user (e.g. UniverSheetWidget bulk edits)
        if (auth()->check()) {
            $user = auth()->user();

            // Filament exposes a canAccessPanel() contract; any user that can
            // reach the admin panel is considered an admin for rate-limit purposes.
            if (method_exists($user, 'canAccessPanel')) {
                // canAccessPanel expects a Panel instance; grab the default panel
                try {
                    $panel = \Filament\Facades\Filament::getCurrentPanel()
                          ?? \Filament\Facades\Filament::getDefaultPanel();

                    if ($panel && $user->canAccessPanel($panel)) {
                        return true;
                    }
                } catch (\Throwable) {
                    // Filament not booted yet — fall through
                }
            }

            // Fallback: check for a simple admin flag / role
            if (property_exists($user, 'is_admin') && $user->is_admin) {
                return true;
            }

            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }
        }

        // 2. Internal flag header — only trust this from server-side requests
        //    (the header is stripped at the edge / load-balancer level)
        if ($request->header('X-Digibase-Internal') === config('app.key')) {
            return true;
        }

        return false;
    }

    /**
     * Apply default rate limit when no API key is present.
     */
    protected function applyDefaultRateLimit(Request $request, Closure $next): Response
    {
        $key = 'api:'.$request->ip();
        $maxAttempts = 60;
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many requests. Please slow down.',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return $next($request);
    }
}
