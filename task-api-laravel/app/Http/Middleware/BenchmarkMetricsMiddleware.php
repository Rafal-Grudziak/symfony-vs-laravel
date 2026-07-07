<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BenchmarkMetricsMiddleware
{
    /**
     * Jeśli w nagłówku żądania zostanie przesłana wartość `X-Benchmark-Metrics: 1`,
     * do odpowiedzi zostaną dodane informacje o liczbie wykonanych zapytań SQL
     * oraz czasie przetwarzania żądania. Dane te są wykorzystywane podczas
     * testów wydajnościowych aplikacji.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->get('X-Benchmark-Metrics') !== '1') {
            return $next($request);
        }

        DB::connection()->enableQueryLog();
        $started = microtime(true);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            DB::connection()->disableQueryLog();

            throw $e;
        }

        $count = count(DB::getQueryLog());
        DB::connection()->disableQueryLog();

        $elapsedMs = (microtime(true) - $started) * 1000;
        $response->headers->set('X-Query-Count', (string) $count);
        $response->headers->set('X-Response-Time-Ms', (string) round($elapsedMs, 3));

        return $response;
    }
}
