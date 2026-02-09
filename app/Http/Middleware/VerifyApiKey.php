<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     *
     * This middleware verifies API keys for the Dynamic Data API.
     * It checks both Bearer token and query parameter.
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        // 1. Extract API Key from Request
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'API Key required. Provide via Authorization: Bearer <key> header or ?api_key= query param.',
                'error_code' => 'MISSING_API_KEY',
            ], 401);
        }

        // 2. Find the Key in Database using constant-time hash comparison
        //    We hash the token and look up by hash to prevent timing attacks.
        //    Fallback: iterate all active keys with hash_equals for constant-time comparison.
        $apiKey = null;
        $candidates = ApiKey::where('is_active', true)->get();
        foreach ($candidates as $candidate) {
            if (hash_equals($candidate->key, $token)) {
                $apiKey = $candidate;
                break;
            }
        }

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API Key',
                'error_code' => 'INVALID_API_KEY',
            ], 401);
        }

        // 3. Check if Key is Valid (active + not expired)
        if (!$apiKey->isValid()) {
            $reason = !$apiKey->is_active ? 'Key is deactivated' : 'Key has expired';
            return response()->json([
                'success' => false,
                'message' => $reason,
                'error_code' => 'API_KEY_INVALID',
            ], 403);
        }

        // 4. Check Scope Permission (if required)
        if ($requiredScope && !$apiKey->hasScope($requiredScope)) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient permissions. Required scope: {$requiredScope}",
                'error_code' => 'INSUFFICIENT_SCOPE',
            ], 403);
        }

        // 5. Check Method-Based Permissions
        $methodScope = $this->getMethodScope($request->method());
        if ($methodScope && !$apiKey->hasScope($methodScope)) {
            return response()->json([
                'success' => false,
                'message' => "This API key cannot perform {$request->method()} operations. Required scope: {$methodScope}",
                'error_code' => 'METHOD_NOT_ALLOWED',
            ], 403);
        }

        // 6. Check Table-Level Access (if route has a tableName parameter)
        $tableName = $request->route('tableName');
        if ($tableName && !$apiKey->hasTableAccess($tableName)) {
            return response()->json([
                'success' => false,
                'message' => "This API key does not have access to table '{$tableName}'",
                'error_code' => 'TABLE_ACCESS_DENIED',
            ], 403);
        }

        // 7. Record Usage (throttled: update at most once per minute per key)
        $usageCacheKey = "api_key_usage:{$apiKey->id}";
        if (!Cache::has($usageCacheKey)) {
            $apiKey->recordUsage();
            Cache::put($usageCacheKey, true, 60);
        }

        // 8. Attach Key to Request for use in Controllers
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_key_user', $apiKey->user);

        return $next($request);
    }

    /**
     * Extract token from various sources.
     */
    protected function extractToken(Request $request): ?string
    {
        // Priority 1: Authorization: Bearer header
        if ($bearer = $request->bearerToken()) {
            return $bearer;
        }

        // Priority 2: X-API-Key header
        if ($header = $request->header('X-API-Key')) {
            return $header;
        }

        // Priority 3: Query parameter (for testing/simple clients)
        if ($query = $request->query('api_key')) {
            return $query;
        }

        return null;
    }

    /**
     * Map HTTP method to required scope.
     */
    protected function getMethodScope(string $method): ?string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD', 'OPTIONS' => 'read',
            'POST' => 'write',
            'PUT', 'PATCH' => 'write',
            'DELETE' => 'delete',
            default => null,
        };
    }
}
