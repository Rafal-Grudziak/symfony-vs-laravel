<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BenchmarkMetricsMiddleware
{
    /**
     * Jeśli w nagłówku żądania zostanie przesłana wartość `X-Benchmark-Metrics: 1`,
     * do odpowiedzi zostaną dodane informacje o liczbie wykonanych zapytań SQL
     * oraz czasie przetwarzania żądania. Dane te są wykorzystywane podczas
     * testów wydajnościowych aplikacji.
     *
     * Opcjonalnie `X-Benchmark-Sql-Log: 1` zapisuje treść SQL + bindingi
     * do storage/logs/sql_benchmark.log (tymczasowa diagnostyka pracy magisterskiej).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $metrics = $request->headers->get('X-Benchmark-Metrics') === '1';
        $sqlLog = $request->headers->get('X-Benchmark-Sql-Log') === '1';

        if (!$metrics && !$sqlLog) {
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

        if ($sqlLog) {
            Log::channel('sql_benchmark')->info('sql_dump', [
                'request_id' => $requestId,
                'framework' => 'laravel',
                'method' => $request->method(),
                'uri' => '/'.$request->path().($request->getQueryString() ? '?'.$request->getQueryString() : ''),
                'query_count' => $count,
                'queries' => array_map(static fn (array $q): array => [
                    'sql' => $q['query'] ?? '',
                    'params' => $q['bindings'] ?? [],
                    'time_ms' => $q['time'] ?? null,
                ], $queryLog),
            ]);
        }

        return $response;
    }
}
