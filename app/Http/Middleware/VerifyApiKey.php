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
     * Verifies API keys using an indexed O(1) hash lookup via key_hash column
     * instead of loading all keys into memory.
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

        // 2. Find the Key via indexed hash lookup — O(1) instead of O(n)
        //    Computes SHA-256 of token, looks up by indexed key_hash column,
        //    then verifies with hash_equals() to prevent timing attacks.
        $apiKey = ApiKey::findByToken($token);

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

        // 5. Check Method-Based Permissions (New Granular System)
        if (!is_null($apiKey->permissions)) {
            $requiredPermission = match (strtoupper($request->method())) {
                'GET', 'HEAD' => 'read',
                'POST' => 'create',
                'PUT', 'PATCH' => 'update',
                'DELETE' => 'delete',
                default => null,
            };

            if ($requiredPermission && !in_array($requiredPermission, $apiKey->permissions)) {
                return response()->json([
                    'success' => false,
                    'message' => "This API Key lacks the '{$requiredPermission}' permission.",
                    'error_code' => 'INSUFFICIENT_PERMISSION',
                ], 403);
            }
        } else {
            // Fallback to Legacy Scope System
            $methodScope = $this->getMethodScope($request->method());
            if ($methodScope && !$apiKey->hasScope($methodScope)) {
                return response()->json([
                    'success' => false,
                    'message' => "This API key cannot perform {$request->method()} operations. Required scope: {$methodScope}",
                    'error_code' => 'METHOD_NOT_ALLOWED',
                ], 403);
            }
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
