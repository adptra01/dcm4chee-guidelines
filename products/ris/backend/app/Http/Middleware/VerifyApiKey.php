<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    /**
     * Authorize X-API-Key access. Reads RIS_API_KEYS (comma-separated).
     * When empty (dev/sandbox), all requests are accepted. When set,
     * requests must carry a matching X-API-Key header (401 otherwise).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $keys = collect(explode(',', (string) env('RIS_API_KEYS', '')))
            ->map(fn (string $k) => trim($k))
            ->filter()
            ->values();

        if ($keys->isEmpty()) {
            return $next($request);
        }

        if (! $keys->contains($request->header('X-API-Key'))) {
            return response()->json(['message' => 'Invalid API key'], 401);
        }

        return $next($request);
    }
}
