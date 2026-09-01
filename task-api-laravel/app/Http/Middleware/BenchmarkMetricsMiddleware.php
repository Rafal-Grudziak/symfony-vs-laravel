<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BenchmarkMetricsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->get('X-Benchmark-Metrics') !== '1') {
            return $next($request);
        }

        DB::connection()->enableQueryLog();
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            DB::connection()->disableQueryLog();

            throw $e;
        }

        $queryLog = DB::getQueryLog();
        $count = count($queryLog);
        DB::connection()->disableQueryLog();

        $elapsedMs = (microtime(true) - $started) * 1000;
        $response->headers->set('X-Query-Count', (string) $count);
        $response->headers->set('X-Response-Time-Ms', (string) round($elapsedMs, 3));
        $response->headers->set('X-Benchmark-Request-Id', $requestId);

        return $response;
    }
}
