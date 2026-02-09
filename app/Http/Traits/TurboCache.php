<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

/**
 * Turbo Cache Trait
 *
 * Smart response caching for Dynamic Data API.
 * Uses a dedicated 'digibase' cache store so invalidation
 * never nukes sessions, routes, or other application cache.
 */
trait TurboCache
{
    protected int $cacheDuration = 300;

    /**
     * Get the dedicated Digibase cache store.
     */
    protected function cacheStore()
    {
        return Cache::store('digibase');
    }

    /**
     * Get cached response or execute callback and cache result.
     */
    protected function cached(string $tableName, Request $request, callable $callback): mixed
    {
        if ($this->shouldSkipCache($request)) {
            return $callback();
        }

        $cacheKey = $this->buildCacheKey($tableName, $request);
        $store = $this->cacheStore();

        if ($this->supportsTagging()) {
            $tags = $this->getCacheTags($tableName);
            return $store->tags($tags)->remember($cacheKey, $this->cacheDuration, $callback);
        }

        return $store->remember($cacheKey, $this->cacheDuration, $callback);
    }

    /**
     * Build a unique cache key based on request parameters + auth context.
     */
    protected function buildCacheKey(string $tableName, Request $request): string
    {
        $params = $request->only([
            'include',
            'search',
            'page',
            'per_page',
            'sort',
            'order',
            'direction',
            'filter',
        ]);

        // Include auth context so RLS-filtered results aren't shared across users
        $authId = auth('sanctum')->id() ?? 'anon';
        $params['_auth'] = $authId;

        // Include API key ID so different keys with different scopes get different caches
        $apiKey = $request->attributes->get('api_key');
        $params['_key'] = $apiKey?->id ?? 'none';

        ksort($params);

        $hash = md5(json_encode($params));

        return "digibase:data:{$tableName}:{$hash}";
    }

    protected function getCacheTags(string $tableName): array
    {
        return [
            'digibase',
            "digibase:{$tableName}",
        ];
    }

    /**
     * Clear cache for a specific table.
     */
    protected function clearTableCache(string $tableName): void
    {
        $store = $this->cacheStore();

        if ($this->supportsTagging()) {
            $store->tags(["digibase:{$tableName}"])->flush();
        } else {
            // Flush only the dedicated digibase store — safe, no collateral damage
            $store->flush();
        }
    }

    /**
     * Clear all Digibase API cache.
     */
    protected function clearAllCache(): void
    {
        $store = $this->cacheStore();

        if ($this->supportsTagging()) {
            $store->tags(['digibase'])->flush();
        } else {
            $store->flush();
        }
    }

    /**
     * Check if the digibase cache store supports tagging.
     */
    protected function supportsTagging(): bool
    {
        $driver = config('cache.stores.digibase.driver', 'file');
        return in_array($driver, ['redis', 'memcached', 'dynamodb']);
    }

    protected function shouldSkipCache(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return true;
        }

        if ($request->boolean('nocache')) {
            return true;
        }

        if ($request->header('Cache-Control') === 'no-cache') {
            return true;
        }

        return false;
    }

    protected function setCacheDuration(int $seconds): self
    {
        $this->cacheDuration = $seconds;
        return $this;
    }

    protected function withCacheHeaders($response, int $maxAge = 60): mixed
    {
        if (method_exists($response, 'header')) {
            $response->header('Cache-Control', "public, max-age={$maxAge}");
            $response->header('X-Cache-Status', 'HIT');
        }
        return $response;
    }
}
