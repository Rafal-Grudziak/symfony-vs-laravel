# Load / stress benchmark report (k6)

- **Started:** 2026-08-26 23:57:15 CEST
- **Finished:** 2026-08-27 00:01:56 CEST
- **Laravel:** `http://localhost:8080`
- **Symfony:** `http://localhost:8081`
- **Endpoint:** `GET /api/tasks?per_page=100&with=project,comments,tags`
- **Executor:** `constant-vus`
- **Measured duration:** 30s (statistics cover only this steady window; no ramp-up/ramp-down)
- **Warm-up:** 10 HTTP requests outside k6 (not included in metrics)
- **VU sleep between requests:** 0.2s

Wartości pochodzą ze standardowych metryk k6 (`http_req_duration`, `http_reqs`, `http_req_failed`, itd.) zebrane wyłącznie w oknie `constant-vus`. Szybsza aplikacja przy tej samej liczbie VU zwykle wykonuje więcej żądań — traktuj `requests_per_second` jako miarę przepustowości.

**HTTP error rate / HTTP failed requests** = `http_req_failed` (błędy transportowe / status inny niż sukces). **Validation failures** = własne metryki (`validation_failures`) dla odpowiedzi HTTP 200 z niepoprawnym lub pustym JSON — nie są wliczane do HTTP error rate.

## 10 VU

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Avg response time (ms) | 47.584 | 740.511 |
| Median response time (ms) | 43.043 | 728.801 |
| P90 response time (ms) | 64.024 | 872.719 |
| P95 response time (ms) | 79.995 | 911.024 |
| Requests per second | 39.834 | 10.474 |
| Total requests | 1203 | 322 |
| HTTP error rate (%) | 0.08% | 0.00% |
| HTTP failed requests | 1 | 0 |
| Validation failures | 0 | 0 |

- Faster avg response: **laravel** (93.6% lower mean)
- Mean time ratio (Laravel / Symfony): **0.064**
- Throughput difference (Laravel − Symfony req/s): **29.359**
- Throughput difference vs Symfony (%): **280.3**
- HTTP error rate difference (Laravel − Symfony, pp): **0.083**

## 50 VU

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Avg response time (ms) | 333.401 | 4374.679 |
| Median response time (ms) | 329.038 | 4633.758 |
| P90 response time (ms) | 376.912 | 4826.897 |
| P95 response time (ms) | 397.136 | 4864.328 |
| Requests per second | 92.463 | 10.189 |
| Total requests | 2822 | 356 |
| HTTP error rate (%) | 0.39% | 0.00% |
| HTTP failed requests | 11 | 0 |
| Validation failures | 2 | 0 |

- Faster avg response: **laravel** (92.4% lower mean)
- Mean time ratio (Laravel / Symfony): **0.076**
- Throughput difference (Laravel − Symfony req/s): **82.274**
- Throughput difference vs Symfony (%): **807.5**
- HTTP error rate difference (Laravel − Symfony, pp): **0.390**

## 100 VU

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Avg response time (ms) | 968.939 | 9300.044 |
| Median response time (ms) | 960.183 | 10628.343 |
| P90 response time (ms) | 1130.535 | 11284.739 |
| P95 response time (ms) | 1168.274 | 11380.961 |
| Requests per second | 83.812 | 9.200 |
| Total requests | 2610 | 375 |
| HTTP error rate (%) | 0.15% | 0.53% |
| HTTP failed requests | 4 | 2 |
| Validation failures | 0 | 1 |

- Faster avg response: **laravel** (89.6% lower mean)
- Mean time ratio (Laravel / Symfony): **0.104**
- Throughput difference (Laravel − Symfony req/s): **74.612**
- Throughput difference vs Symfony (%): **811.0**
- HTTP error rate difference (Laravel − Symfony, pp): **-0.380**

## Summary

| VU | Laravel avg | Symfony avg | Laravel P95 | Symfony P95 | Laravel req/s | Symfony req/s | Laravel HTTP errors | Symfony HTTP errors |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 10 | 47.584 | 740.511 | 79.995 | 911.024 | 39.834 | 10.474 | 0.08% | 0.00% |
| 50 | 333.401 | 4374.679 | 397.136 | 4864.328 | 92.463 | 10.189 | 0.39% | 0.00% |
| 100 | 968.939 | 9300.044 | 1168.274 | 11380.961 | 83.812 | 9.200 | 0.15% | 0.53% |

## Artifacts

- Results directory: `/Users/rafal/code/symfony-vs-laravel/load_benchmark_results`
- Summary CSV: `/Users/rafal/code/symfony-vs-laravel/load_benchmark_results/benchmark_summary.csv`
- Per-scenario: `{framework}_{vus}_summary.json`, `_raw.json`, `_report.txt`, `_metrics.csv`
