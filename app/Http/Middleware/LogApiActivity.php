<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminable middleware: logs every API hit to the api_analytics table.
 *
 * The terminate() method runs AFTER the response has been sent to the client,
 * so the database insert never adds latency to the user's request.
 */
class LogApiActivity
{
    protected float $startTime;

    public function handle(Request $request, Closure $next): Response
    {
        $this->startTime = microtime(true);

        return $next($request);
    }

    /**
     * Called after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! isset($this->startTime)) {
                return;
            }
            $durationMs = (int) round((microtime(true) - $this->startTime) * 1000);

            // Extract table name from the route parameter
            $tableName = $request->route('tableName') ?? '-';

            DB::table('api_analytics')->insert([
                'user_id' => auth('sanctum')->id() ?? auth()->id(),
                'table_name' => $tableName,
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let analytics logging break the application
            Log::warning('API analytics logging failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
