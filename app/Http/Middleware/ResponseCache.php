<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ResponseCache
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('cache.response');
        $enabled = (bool) ($config['enabled'] ?? false);

        if (!$enabled || !$request->isMethod('GET')) {
            return $next($request);
        }

        $userId = $request->user()?->id ?? 'guest';
        $accept = $request->header('Accept', '');
        $cacheKey = 'response_cache:' . $userId . ':' . md5($request->fullUrl() . '|' . $accept);
        $ttl = (int) ($config['ttl_seconds'] ?? 120);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['content'], $cached['status'], $cached['headers'])) {
            return response($cached['content'], $cached['status'])->withHeaders($cached['headers']);
        }

        $response = $next($request);

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        if ($response->headers->has('Set-Cookie')) {
            return $response;
        }

        $headers = $response->headers->all();
        unset($headers['set-cookie']);

        Cache::put($cacheKey, [
            'content' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $headers,
        ], $ttl);

        return $response;
    }
}
