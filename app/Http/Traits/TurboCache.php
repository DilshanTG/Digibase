<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

/**
 * Turbo Cache Trait
 * 
 * Smart response caching for Dynamic Data API.
 * Provides instant responses (~10ms) instead of database queries (~100ms+).
 */
trait TurboCache
{
    /**
     * Default cache duration in seconds (5 minutes).
     */
    protected int $cacheDuration = 300;

    /**
     * Get cached response or execute callback and cache result.
     *
     * @param string $tableName The table/model name
     * @param Request $request The HTTP request (for query params)
     * @param callable $callback The function to execute if cache miss
     * @return mixed
     */
    protected function cached(string $tableName, Request $request, callable $callback): mixed
    {
        // Skip cache for authenticated mutations or when explicitly disabled
        if ($this->shouldSkipCache($request)) {
            return $callback();
        }

        $cacheKey = $this->buildCacheKey($tableName, $request);
        $tags = $this->getCacheTags($tableName);

        // Use tagged cache if driver supports it (Redis, Memcached)
        if ($this->supportsTagging()) {
            return Cache::tags($tags)->remember($cacheKey, $this->cacheDuration, $callback);
        }

        // Fallback for file/database cache
        return Cache::remember($cacheKey, $this->cacheDuration, $callback);
    }

    /**
     * Build a unique cache key based on request parameters.
     */
    protected function buildCacheKey(string $tableName, Request $request): string
    {
        $params = $request->only([
            'include',      // Relationships
            'search',       // Search term
            'page',         // Pagination
            'per_page',     // Items per page
            'sort',         // Sort column
            'order',        // Sort direction
            'direction',    // Sort direction (actual param used in controller)
            'filter',       // Filters
        ]);

        // Include auth context so RLS-filtered results aren't shared across users
        $authId = auth('sanctum')->id() ?? 'anon';
        $params['_auth'] = $authId;

        // Include API key ID so different keys with different scopes get different caches
        $apiKey = $request->attributes->get('api_key');
        $params['_key'] = $apiKey?->id ?? 'none';

        // Sort params for consistent key generation
        ksort($params);

        $hash = md5(json_encode($params));

        return "digibase:data:{$tableName}:{$hash}";
    }

    /**
     * Get cache tags for a table.
     */
    protected function getCacheTags(string $tableName): array
    {
        return [
            'digibase',           // Global tag
            "digibase:{$tableName}", // Table-specific tag
        ];
    }

    /**
     * Clear cache for a specific table.
     * Call this after create/update/delete operations.
     */
    protected function clearTableCache(string $tableName): void
    {
        if ($this->supportsTagging()) {
            Cache::tags(["digibase:{$tableName}"])->flush();
        } else {
            // For non-tagging drivers, we need pattern-based clearing
            // This is less efficient but works with file cache
            $this->clearCacheByPattern("digibase:data:{$tableName}:*");
        }
    }

    /**
     * Clear all Digibase API cache.
     */
    protected function clearAllCache(): void
    {
        if ($this->supportsTagging()) {
            Cache::tags(['digibase'])->flush();
        } else {
            $this->clearCacheByPattern("digibase:*");
        }
    }

    /**
     * Clear cache entries matching a pattern (for non-tagging drivers).
     */
    protected function clearCacheByPattern(string $pattern): void
    {
        // For file cache, we can't easily clear by pattern
        // Best effort: clear the entire cache
        // In production, use Redis for proper tagging support
        if (config('cache.default') === 'file') {
            // Clear all cache - not ideal but works
            Cache::flush();
        }
    }

    /**
     * Check if cache driver supports tagging.
     */
    protected function supportsTagging(): bool
    {
        $driver = config('cache.default');
        return in_array($driver, ['redis', 'memcached', 'dynamodb']);
    }

    /**
     * Determine if cache should be skipped for this request.
     */
    protected function shouldSkipCache(Request $request): bool
    {
        // Skip for non-GET requests
        if (!$request->isMethod('GET')) {
            return true;
        }

        // Skip if ?nocache=1 is passed
        if ($request->boolean('nocache')) {
            return true;
        }

        // Skip if Cache-Control: no-cache header is present
        if ($request->header('Cache-Control') === 'no-cache') {
            return true;
        }

        return false;
    }

    /**
     * Set custom cache duration.
     */
    protected function setCacheDuration(int $seconds): self
    {
        $this->cacheDuration = $seconds;
        return $this;
    }

    /**
     * Add cache headers to response for client-side caching.
     */
    protected function withCacheHeaders($response, int $maxAge = 60): mixed
    {
        if (method_exists($response, 'header')) {
            $response->header('Cache-Control', "public, max-age={$maxAge}");
            $response->header('X-Cache-Status', 'HIT');
        }
        return $response;
    }
}
