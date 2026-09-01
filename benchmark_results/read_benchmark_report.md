# Read operations benchmark report

- **Started:** 2026-08-26 22:55:21 CEST
- **Finished:** 2026-08-26 22:57:21 CEST
- **Laravel:** `http://localhost:8080`
- **Symfony:** `http://localhost:8081`
- **Warm-up requests per framework/scenario:** 5
- **Measured requests per framework/scenario:** 30
- **Task id used in single-resource scenarios:** 1

## Scenarios

### list_per_page_15

GET /api/tasks?per_page=15 — lista bez relacji

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 4.482 | 12.051 |
| Median response time (ms) | 3.679 | 9.069 |
| Min response time (ms) | 3.269 | 7.821 |
| Max response time (ms) | 15.830 | 29.630 |
| Stddev response time (ms) | 2.599 | 6.847 |
| P90 response time (ms) | 4.471 | 25.393 |
| P95 response time (ms) | 11.136 | 29.619 |
| Avg SQL queries | 2.000 | 3.000 |
| Min SQL queries | 2.000 | 3.000 |
| Max SQL queries | 2.000 | 3.000 |
| Avg response size (bytes) | 4965.0 | 4063.0 |

- Faster framework (lower avg ms): **laravel** (62.8% lower mean)
- SQL query avg difference (Laravel − Symfony): **-1.000**
- Mean response time ratio (Laravel / Symfony): **0.372**

### list_per_page_100

GET /api/tasks?per_page=100 — lista bez relacji

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 7.478 | 11.277 |
| Median response time (ms) | 5.264 | 9.274 |
| Min response time (ms) | 4.785 | 8.843 |
| Max response time (ms) | 63.672 | 25.249 |
| Stddev response time (ms) | 10.649 | 4.620 |
| P90 response time (ms) | 6.563 | 20.139 |
| P95 response time (ms) | 8.695 | 24.192 |
| Avg SQL queries | 2.000 | 3.000 |
| Min SQL queries | 2.000 | 3.000 |
| Max SQL queries | 2.000 | 3.000 |
| Avg response size (bytes) | 22829.0 | 21922.0 |

- Faster framework (lower avg ms): **laravel** (33.7% lower mean)
- SQL query avg difference (Laravel − Symfony): **-1.000**
- Mean response time ratio (Laravel / Symfony): **0.663**

### single_task

GET /api/tasks/1 — pojedyncze zadanie bez relacji

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 2.090 | 6.221 |
| Median response time (ms) | 1.862 | 4.516 |
| Min response time (ms) | 1.620 | 4.300 |
| Max response time (ms) | 6.456 | 16.588 |
| Stddev response time (ms) | 0.861 | 3.855 |
| P90 response time (ms) | 2.187 | 11.507 |
| P95 response time (ms) | 2.748 | 16.236 |
| Avg SQL queries | 1.000 | 1.000 |
| Min SQL queries | 1.000 | 1.000 |
| Max SQL queries | 1.000 | 1.000 |
| Avg response size (bytes) | 212.0 | 212.0 |

- Faster framework (lower avg ms): **laravel** (66.4% lower mean)
- SQL query avg difference (Laravel − Symfony): **0.000**
- Mean response time ratio (Laravel / Symfony): **0.336**

### list_with_project

GET /api/tasks?per_page=15&with=project

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 4.559 | 14.353 |
| Median response time (ms) | 4.312 | 10.970 |
| Min response time (ms) | 3.637 | 10.557 |
| Max response time (ms) | 10.541 | 49.877 |
| Stddev response time (ms) | 1.214 | 8.543 |
| P90 response time (ms) | 4.767 | 26.672 |
| P95 response time (ms) | 5.619 | 28.537 |
| Avg SQL queries | 3.000 | 3.000 |
| Min SQL queries | 3.000 | 3.000 |
| Max SQL queries | 3.000 | 3.000 |
| Avg response size (bytes) | 9688.0 | 6997.0 |

- Faster framework (lower avg ms): **laravel** (68.2% lower mean)
- SQL query avg difference (Laravel − Symfony): **0.000**
- Mean response time ratio (Laravel / Symfony): **0.318**

### list_with_comments

GET /api/tasks?per_page=15&with=comments — Lista 15 zadań wraz z komentarzami

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 5.232 | 36.865 |
| Median response time (ms) | 5.072 | 32.833 |
| Min response time (ms) | 4.761 | 31.377 |
| Max response time (ms) | 9.897 | 89.663 |
| Stddev response time (ms) | 0.895 | 11.145 |
| P90 response time (ms) | 5.361 | 45.832 |
| P95 response time (ms) | 5.469 | 45.953 |
| Avg SQL queries | 3.000 | 3.000 |
| Min SQL queries | 3.000 | 3.000 |
| Max SQL queries | 3.000 | 3.000 |
| Avg response size (bytes) | 25614.0 | 26272.0 |

- Faster framework (lower avg ms): **laravel** (85.8% lower mean)
- SQL query avg difference (Laravel − Symfony): **0.000**
- Mean response time ratio (Laravel / Symfony): **0.142**

### list_with_tags

GET /api/tasks?per_page=15&with=tags — Lista 15 zadań wraz ze znacznikami

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 5.560 | 43.942 |
| Median response time (ms) | 5.305 | 41.606 |
| Min response time (ms) | 4.967 | 40.330 |
| Max response time (ms) | 10.706 | 59.235 |
| Stddev response time (ms) | 1.109 | 5.060 |
| P90 response time (ms) | 5.593 | 53.974 |
| P95 response time (ms) | 8.111 | 54.452 |
| Avg SQL queries | 3.000 | 3.000 |
| Min SQL queries | 3.000 | 3.000 |
| Max SQL queries | 3.000 | 3.000 |
| Avg response size (bytes) | 10227.0 | 10884.0 |

- Faster framework (lower avg ms): **laravel** (87.3% lower mean)
- SQL query avg difference (Laravel − Symfony): **0.000**
- Mean response time ratio (Laravel / Symfony): **0.127**

### list_with_all

GET /api/tasks?per_page=15&with=project,comments,tags

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 8.104 | 183.359 |
| Median response time (ms) | 7.495 | 177.630 |
| Min response time (ms) | 7.260 | 174.375 |
| Max response time (ms) | 19.418 | 228.733 |
| Stddev response time (ms) | 2.288 | 12.993 |
| P90 response time (ms) | 8.506 | 192.714 |
| P95 response time (ms) | 11.687 | 222.887 |
| Avg SQL queries | 5.000 | 3.000 |
| Min SQL queries | 5.000 | 3.000 |
| Max SQL queries | 5.000 | 3.000 |
| Avg response size (bytes) | 35503.0 | 35899.0 |

- Faster framework (lower avg ms): **laravel** (95.6% lower mean)
- SQL query avg difference (Laravel − Symfony): **2.000**
- Mean response time ratio (Laravel / Symfony): **0.044**

### list_100_with_all

GET /api/tasks?per_page=100&with=project,comments,tags

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 27.308 | 209.143 |
| Median response time (ms) | 26.516 | 205.312 |
| Min response time (ms) | 26.124 | 199.838 |
| Max response time (ms) | 40.323 | 275.753 |
| Stddev response time (ms) | 2.794 | 13.612 |
| P90 response time (ms) | 27.159 | 213.161 |
| P95 response time (ms) | 33.542 | 221.713 |
| Avg SQL queries | 5.000 | 3.000 |
| Min SQL queries | 5.000 | 3.000 |
| Max SQL queries | 5.000 | 3.000 |
| Avg response size (bytes) | 236925.0 | 248188.0 |

- Faster framework (lower avg ms): **laravel** (86.9% lower mean)
- SQL query avg difference (Laravel − Symfony): **2.000**
- Mean response time ratio (Laravel / Symfony): **0.131**

### single_with_all

GET /api/tasks/1?with=project,comments,tags

| Metric | Laravel | Symfony |
| --- | --- | --- |
| Valid measurements | 30 | 30 |
| Errors | 0 | 0 |
| Avg response time (ms) | 3.718 | 9.049 |
| Median response time (ms) | 3.519 | 6.991 |
| Min response time (ms) | 3.400 | 6.527 |
| Max response time (ms) | 8.285 | 23.838 |
| Stddev response time (ms) | 0.876 | 5.083 |
| P90 response time (ms) | 3.753 | 20.831 |
| P95 response time (ms) | 4.177 | 20.933 |
| Avg SQL queries | 5.000 | 2.000 |
| Min SQL queries | 5.000 | 2.000 |
| Max SQL queries | 5.000 | 2.000 |
| Avg response size (bytes) | 2397.0 | 3262.0 |

- Faster framework (lower avg ms): **laravel** (58.9% lower mean)
- SQL query avg difference (Laravel − Symfony): **3.000**
- Mean response time ratio (Laravel / Symfony): **0.411**

## Summary

| Scenario | Laravel avg ms | Symfony avg ms | Laravel median ms | Symfony median ms | Laravel p95 ms | Symfony p95 ms | Laravel SQL | Symfony SQL | Laravel avg response size | Symfony avg response size | Faster framework | Difference % |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| list_per_page_15 | 4.482 | 12.051 | 3.679 | 9.069 | 11.136 | 29.619 | 2.000 | 3.000 | 4965.0 | 4063.0 | laravel | 62.8% |
| list_per_page_100 | 7.478 | 11.277 | 5.264 | 9.274 | 8.695 | 24.192 | 2.000 | 3.000 | 22829.0 | 21922.0 | laravel | 33.7% |
| single_task | 2.090 | 6.221 | 1.862 | 4.516 | 2.748 | 16.236 | 1.000 | 1.000 | 212.0 | 212.0 | laravel | 66.4% |
| list_with_project | 4.559 | 14.353 | 4.312 | 10.970 | 5.619 | 28.537 | 3.000 | 3.000 | 9688.0 | 6997.0 | laravel | 68.2% |
| list_with_comments | 5.232 | 36.865 | 5.072 | 32.833 | 5.469 | 45.953 | 3.000 | 3.000 | 25614.0 | 26272.0 | laravel | 85.8% |
| list_with_tags | 5.560 | 43.942 | 5.305 | 41.606 | 8.111 | 54.452 | 3.000 | 3.000 | 10227.0 | 10884.0 | laravel | 87.3% |
| list_with_all | 8.104 | 183.359 | 7.495 | 177.630 | 11.687 | 222.887 | 5.000 | 3.000 | 35503.0 | 35899.0 | laravel | 95.6% |
| list_100_with_all | 27.308 | 209.143 | 26.516 | 205.312 | 33.542 | 221.713 | 5.000 | 3.000 | 236925.0 | 248188.0 | laravel | 86.9% |
| single_with_all | 3.718 | 9.049 | 3.519 | 6.991 | 4.177 | 20.933 | 5.000 | 2.000 | 2397.0 | 3262.0 | laravel | 58.9% |

## Automatic observations

Obserwacje oparte wyłącznie na zmierzonych wartościach (bez spekulacji o przyczynach):

- Niższy średni czas odpowiedzi: Laravel w 9 scenariuszach, Symfony w 0 scenariuszach.
- list_per_page_15: Laravel wykonał średnio mniej zapytań SQL (2.00 vs 3.00).
- list_per_page_100: Laravel wykonał średnio mniej zapytań SQL (2.00 vs 3.00).
- single_task: średnia liczba zapytań SQL była taka sama (1.00).
- list_with_project: średnia liczba zapytań SQL była taka sama (3.00).
- list_with_comments: średnia liczba zapytań SQL była taka sama (3.00).
- list_with_tags: średnia liczba zapytań SQL była taka sama (3.00).
- list_with_all: Symfony wykonał średnio mniej zapytań SQL (3.00 vs 5.00).
- list_100_with_all: Symfony wykonał średnio mniej zapytań SQL (3.00 vs 5.00).
- single_with_all: Symfony wykonał średnio mniej zapytań SQL (2.00 vs 5.00).
- laravel: wzrost per_page z 15 do 100 (bez relacji) zwiększył średni czas z 4.482 ms do 7.478 ms.
- symfony: wzrost per_page z 15 do 100 (bez relacji) zmniejszył średni czas z 12.051 ms do 11.277 ms.
- laravel: dołączenie with=project zwiększyło średnią liczbę zapytań SQL z 2.00 do 3.00.
- laravel: dołączenie with=project,comments,tags zwiększyło średnią liczbę zapytań SQL z 2.00 do 5.00.
- list_per_page_15/laravel: duży rozrzut czasu odpowiedzi (stddev 2.599 ms przy średniej 4.482 ms; CV=0.58).
- list_per_page_15/symfony: duży rozrzut czasu odpowiedzi (stddev 6.847 ms przy średniej 12.051 ms; CV=0.57).
- list_per_page_100/laravel: duży rozrzut czasu odpowiedzi (stddev 10.649 ms przy średniej 7.478 ms; CV=1.42).
- list_per_page_100/symfony: duży rozrzut czasu odpowiedzi (stddev 4.620 ms przy średniej 11.277 ms; CV=0.41).
- single_task/laravel: duży rozrzut czasu odpowiedzi (stddev 0.861 ms przy średniej 2.090 ms; CV=0.41).
- single_task/symfony: duży rozrzut czasu odpowiedzi (stddev 3.855 ms przy średniej 6.221 ms; CV=0.62).
- list_with_project/laravel: duży rozrzut czasu odpowiedzi (stddev 1.214 ms przy średniej 4.559 ms; CV=0.27).
- list_with_project/symfony: duży rozrzut czasu odpowiedzi (stddev 8.543 ms przy średniej 14.353 ms; CV=0.60).
- list_with_comments/symfony: duży rozrzut czasu odpowiedzi (stddev 11.145 ms przy średniej 36.865 ms; CV=0.30).
- list_with_all/laravel: duży rozrzut czasu odpowiedzi (stddev 2.288 ms przy średniej 8.104 ms; CV=0.28).
- single_with_all/symfony: duży rozrzut czasu odpowiedzi (stddev 5.083 ms przy średniej 9.049 ms; CV=0.56).

## Relation comparison

Zestawienie wpływu poszczególnych relacji na średni czas odpowiedzi oraz średnią liczbę zapytań SQL (scenariusze `per_page=15`). Wyłącznie dane pomiarowe:

| Relacja | Laravel avg | Symfony avg | Laravel SQL | Symfony SQL |
| --- | --- | --- | --- | --- |
| comments | 5.232 | 36.865 | 3.000 | 3.000 |
| tags | 5.560 | 43.942 | 3.000 | 3.000 |
| project | 4.559 | 14.353 | 3.000 | 3.000 |
| project+comments+tags | 8.104 | 183.359 | 5.000 | 3.000 |

## Artifacts

- Raw results: `/Users/rafal/code/symfony-vs-laravel/read_benchmark_results.csv`
- Summary CSV: `/Users/rafal/code/symfony-vs-laravel/read_benchmark_summary.csv`
- This report: `/Users/rafal/code/symfony-vs-laravel/read_benchmark_report.md`
