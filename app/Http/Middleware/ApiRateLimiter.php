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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->attributes->get('api_key');
        
        if (!$apiKey) {
            // Fallback to default rate limit if no API key
            return $this->applyDefaultRateLimit($request, $next);
        }

        // Get rate limit from API key (default to 60 if not set)
        $maxAttempts = $apiKey->rate_limit ?? 60;
        $decayMinutes = 1;

        // Create unique key for this API key
        $key = 'api:' . $apiKey->id;

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
     * Apply default rate limit when no API key is present.
     */
    protected function applyDefaultRateLimit(Request $request, Closure $next): Response
    {
        $key = 'api:' . $request->ip();
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
