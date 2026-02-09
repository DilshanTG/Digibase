<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        // 2. Find the Key in Database
        $apiKey = ApiKey::where('key', $token)->first();

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

        // 6. Record Usage (async-friendly: don't block request)
        $apiKey->recordUsage();

        // 7. Attach Key to Request for use in Controllers
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
