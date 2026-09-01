# ORM isolation benchmark report

- **Started:** 2026-08-26 23:18:29 CEST
- **Finished:** 2026-08-26 23:20:27 CEST
- **Laravel:** `http://localhost:8080`
- **Symfony:** `http://localhost:8081`
- **Warm-up / measured:** 5 / 30
- **Endpoint prefix:** `/api/benchmark/no-orm/tasks`

Control experiment: same HTTP/framework/serialization path with **in-memory data** (no ORM SQL).
Every request must report `X-Query-Count: 0` (otherwise the run aborts).

Interpretation note: `full − no_orm` approximates cost of the removed data-access path,
not a laboratory measurement of Doctrine vs Eloquent in isolation.

## list_per_page_15

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Avg response time (ms) | 5.558 | 4.908 |
| Median response time (ms) | 5.320 | 4.002 |
| Min response time (ms) | 4.936 | 3.816 |
| Max response time (ms) | 10.123 | 10.456 |
| Stddev response time (ms) | 0.945 | 2.151 |
| P95 response time (ms) | 6.458 | 10.365 |
| Avg SQL queries | 0.000 | 0.000 |
| Avg response size (bytes) | 5802.0 | 4748.0 |

## list_per_page_100

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Avg response time (ms) | 6.954 | 4.929 |
| Median response time (ms) | 6.700 | 4.053 |
| Min response time (ms) | 6.470 | 3.893 |
| Max response time (ms) | 9.578 | 10.550 |
| Stddev response time (ms) | 0.676 | 2.153 |
| P95 response time (ms) | 8.273 | 10.376 |
| Avg SQL queries | 0.000 | 0.000 |
| Avg response size (bytes) | 26730.0 | 25671.0 |

## single_task

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Avg response time (ms) | 4.459 | 4.625 |
| Median response time (ms) | 4.287 | 3.855 |
| Min response time (ms) | 4.181 | 3.729 |
| Max response time (ms) | 7.169 | 10.932 |
| Stddev response time (ms) | 0.542 | 1.924 |
| P95 response time (ms) | 4.946 | 9.699 |
| Avg SQL queries | 0.000 | 0.000 |
| Avg response size (bytes) | 251.0 | 251.0 |

## list_with_project

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Avg response time (ms) | 6.279 | 4.930 |
| Median response time (ms) | 5.367 | 4.004 |
| Min response time (ms) | 5.137 | 3.880 |
| Max response time (ms) | 26.225 | 10.621 |
| Stddev response time (ms) | 3.803 | 2.042 |
| P95 response time (ms) | 8.357 | 10.273 |
| Avg SQL queries | 0.000 | 0.000 |
| Avg response size (bytes) | 9250.0 | 10322.0 |

## list_with_comments

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Avg response time (ms) | 6.402 | 4.957 |
| Median response time (ms) | 6.152 | 4.043 |
| Min response time (ms) | 6.001 | 3.808 |
| Max response time (ms) | 10.827 | 10.400 |
| Stddev response time (ms) | 0.850 | 2.146 |
| P95 response time (ms) | 6.691 | 10.367 |
| Avg SQL queries | 0.000 | 0.000 |
| Avg response size (bytes) | 16922.0 | 15796.0 |

## list_with_tags

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Avg response time (ms) | 5.850 | 4.967 |
| Median response time (ms) | 5.608 | 3.970 |
| Min response time (ms) | 5.378 | 3.797 |
| Max response time (ms) | 10.258 | 12.894 |
| Stddev response time (ms) | 0.848 | 2.320 |
| P95 response time (ms) | 6.239 | 10.338 |
| Avg SQL queries | 0.000 | 0.000 |
| Avg response size (bytes) | 9997.0 | 8903.0 |

## list_with_all

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Avg response time (ms) | 7.149 | 4.933 |
| Median response time (ms) | 6.999 | 4.069 |
| Min response time (ms) | 6.819 | 3.849 |
| Max response time (ms) | 10.014 | 11.656 |
| Stddev response time (ms) | 0.582 | 2.115 |
| P95 response time (ms) | 7.812 | 10.458 |
| Avg SQL queries | 0.000 | 0.000 |
| Avg response size (bytes) | 24469.0 | 25397.0 |

## create_task

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Avg response time (ms) | 4.843 | 12.340 |
| Median response time (ms) | 4.624 | 10.054 |
| Min response time (ms) | 4.518 | 9.702 |
| Max response time (ms) | 9.453 | 24.553 |
| Stddev response time (ms) | 0.869 | 5.039 |
| P95 response time (ms) | 5.011 | 23.806 |
| Avg SQL queries | 0.000 | 0.000 |
| Avg response size (bytes) | 514.0 | 514.0 |

## update_task

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Avg response time (ms) | 5.034 | 11.951 |
| Median response time (ms) | 4.662 | 10.078 |
| Min response time (ms) | 4.554 | 9.375 |
| Max response time (ms) | 9.453 | 23.912 |
| Stddev response time (ms) | 1.068 | 4.521 |
| P95 response time (ms) | 7.075 | 23.294 |
| Avg SQL queries | 0.000 | 0.000 |
| Avg response size (bytes) | 500.0 | 500.0 |

## delete_task

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Avg response time (ms) | 0.414 | 1.360 |
| Median response time (ms) | 0.359 | 1.047 |
| Min response time (ms) | 0.279 | 0.934 |
| Max response time (ms) | 2.065 | 4.085 |
| Stddev response time (ms) | 0.311 | 0.821 |
| P95 response time (ms) | 0.484 | 3.394 |
| Avg SQL queries | 0.000 | 0.000 |
| Avg response size (bytes) | 0.0 | 0.0 |

## Summary (no-ORM)

| Scenario | Laravel avg | Symfony avg | Laravel P95 | Symfony P95 | Laravel SQL | Symfony SQL |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| list_per_page_15 | 5.558 | 4.908 | 6.458 | 10.365 | 0 | 0 |
| list_per_page_100 | 6.954 | 4.929 | 8.273 | 10.376 | 0 | 0 |
| single_task | 4.459 | 4.625 | 4.946 | 9.699 | 0 | 0 |
| list_with_project | 6.279 | 4.930 | 8.357 | 10.273 | 0 | 0 |
| list_with_comments | 6.402 | 4.957 | 6.691 | 10.367 | 0 | 0 |
| list_with_tags | 5.850 | 4.967 | 6.239 | 10.338 | 0 | 0 |
| list_with_all | 7.149 | 4.933 | 7.812 | 10.458 | 0 | 0 |
| create_task | 4.843 | 12.340 | 5.011 | 23.806 | 0 | 0 |
| update_task | 5.034 | 11.951 | 7.075 | 23.294 | 0 | 0 |
| delete_task | 0.414 | 1.360 | 0.484 | 3.394 | 0 | 0 |

## Comparison: full endpoints vs no-ORM (avg ms)

| Scenario | Laravel full | Laravel no ORM | Symfony full | Symfony no ORM |
| --- | ---: | ---: | ---: | ---: |
| list_per_page_15 | 4.482 | 5.558 | 12.051 | 4.908 |
| list_per_page_100 | 7.478 | 6.954 | 11.277 | 4.929 |
| single_task | 2.090 | 4.459 | 6.221 | 4.625 |
| list_with_project | 4.559 | 6.279 | 14.353 | 4.930 |
| list_with_comments | 5.232 | 6.402 | 36.865 | 4.957 |
| list_with_tags | 5.560 | 5.850 | 43.942 | 4.967 |
| list_with_all | 8.104 | 7.149 | 183.359 | 4.933 |
| create_task | 4.893 | 4.843 | 16.925 | 12.340 |
| update_task | 5.276 | 5.034 | 15.642 | 11.951 |
| delete_task | 3.165 | 0.414 | 6.408 | 1.360 |

## Laravel timer scope fix (read scenarios, avg ms)

After moving `BenchmarkMetricsMiddleware` before `SubstituteBindings`, Laravel full read timings include route model binding and comparable request setup.

_No pre-timer snapshot at `read_benchmark_summary_pre_timer_fix.csv`._

## Approximate path cost removed in no-ORM (`full − no_orm`)

Values below approximate the cost of the **removed data-access path** (ORM + DB),
not a direct measurement of Doctrine vs Eloquent in isolation.

| Scenario | Framework | full (ms) | no_orm (ms) | delta (ms) | share of full (%) |
| --- | --- | ---: | ---: | ---: | ---: |
| list_per_page_15 | Laravel | 4.482 | 5.558 | -1.076 | -24.0 |
| list_per_page_15 | Symfony | 12.051 | 4.908 | 7.143 | 59.3 |
| list_per_page_100 | Laravel | 7.478 | 6.954 | 0.524 | 7.0 |
| list_per_page_100 | Symfony | 11.277 | 4.929 | 6.348 | 56.3 |
| single_task | Laravel | 2.090 | 4.459 | -2.369 | -113.4 |
| single_task | Symfony | 6.221 | 4.625 | 1.597 | 25.7 |
| list_with_project | Laravel | 4.559 | 6.279 | -1.720 | -37.7 |
| list_with_project | Symfony | 14.353 | 4.930 | 9.423 | 65.7 |
| list_with_comments | Laravel | 5.232 | 6.402 | -1.170 | -22.4 |
| list_with_comments | Symfony | 36.865 | 4.957 | 31.908 | 86.6 |
| list_with_tags | Laravel | 5.560 | 5.850 | -0.290 | -5.2 |
| list_with_tags | Symfony | 43.942 | 4.967 | 38.975 | 88.7 |
| list_with_all | Laravel | 8.104 | 7.149 | 0.955 | 11.8 |
| list_with_all | Symfony | 183.359 | 4.933 | 178.426 | 97.3 |
| create_task | Laravel | 4.893 | 4.843 | 0.051 | 1.0 |
| create_task | Symfony | 16.925 | 12.340 | 4.585 | 27.1 |
| update_task | Laravel | 5.276 | 5.034 | 0.242 | 4.6 |
| update_task | Symfony | 15.642 | 11.951 | 3.690 | 23.6 |
| delete_task | Laravel | 3.165 | 0.414 | 2.752 | 86.9 |
| delete_task | Symfony | 6.408 | 1.360 | 5.048 | 78.8 |

## Methodological limits

- No-ORM still uses Eloquent/Doctrine **classes** for serialization shapes, but not the query unit of work / DB.
- Store/update skip DB `exists` checks present on production endpoints.
- In-memory payloads are deterministic fixtures (5 comments, 2 tags), not a sample of seed data.
- Full vs no-ORM timings come from separate runs; treat deltas as approximate.
- This is **not** a direct Doctrine vs Eloquent micro-benchmark.

## Artifacts

- Raw CSV: `/Users/rafal/code/symfony-vs-laravel/orm_isolation_benchmark_results.csv`
- Summary CSV: `/Users/rafal/code/symfony-vs-laravel/orm_isolation_benchmark_summary.csv`
- This report: `/Users/rafal/code/symfony-vs-laravel/orm_isolation_benchmark_report.md`
