# Benchmarki k6 (Symfony vs Laravel)

Powtarzalne testy obciążeniowe dla JSON API do zarządzania zadaniami. Te same skrypty działają na **dowolnym** adresie HTTP - wystarczy ustawić `BASE_URL` na Symfony (`http://localhost:8081`) albo na odpowiednik w Laravelu (`benchmark/comparison.env.laravel.example`). Ścieżki i payloady zostają bez zmian.

## Co mierzymy

| Źródło | Co to znaczy |
|--------|----------------|
| **k6 `http_req_duration`** | Czas end-to-end od strony klienta (DNS, TCP, TLS jeśli jest, request, response, PHP, baza, serializacja). |
| **`http_reqs` rate** | Ile requestów na sekundę widzi generator obciążenia. |
| **`data_received` / `data_sent` rate** | Przepustowość ciał HTTP (bajty/s). |
| **`http_req_failed` rate** | k6 uznaje request za nieudany (timeout, domyślnie też non-2xx itd.). |
| **`checks` pass rate** | Asercje ze skryptów (np. HTTP 200 na listach). |
| **`X-Query-Count`** (Trend `x_query_count`) | Ile zapytań SQL wykonał serwer - gdy wysyłasz `X-Benchmark-Metrics: 1`. |
| **`X-Response-Time-Ms`** (Trend `x_response_time_ms`) | Czas aplikacji (wall clock) raportowany przez serwer dla tych samych requestów. |
| **Liczniki statusów HTTP** | `http_status_200`, `http_status_201`, … oraz `http_status_other` dla rzadszych kodów. |

Każdy skrypt wysyła `X-Benchmark-Metrics: 1`, żeby Symfony (i zgodna implementacja w Laravelu) mogły zwracać nagłówki benchmarkowe.

## Wymagania wstępne

1. **Stack** - PHP, Nginx, MySQL (plik `docker-compose.yml` z tego repozytorium).
2. **Migracje** - `docker compose -f docker-compose.yml exec app php bin/console doctrine:migrations:migrate --no-interaction`
3. **Dane w skali pracy dyplomowej** - ten sam rząd wielkości co seeder Laravela:

   ```bash
   docker compose -f docker-compose.yml exec app \
     php -d memory_limit=512M bin/console doctrine:fixtures:load --group=thesis --append --no-interaction
   ```

   `--append` pomijaj tylko wtedy, gdy **celowo** chcesz najpierw wyczyścić bazę.

### Uwaga o pliku Compose

W projekcie jest też `compose.yaml` z Symfony Flex (stub Postgresa). Do stacku Task API i k6 **zawsze** używaj `-f docker-compose.yml` - inaczej Docker Compose może wybrać zły plik. Skrypt `benchmark/run-compose-k6.sh` i tak przekazuje `-f docker-compose.yml`.

## Zmienne środowiskowe

| Zmienna | Domyślnie | Opis |
|---------|-----------|------|
| `BASE_URL` | `http://localhost:8081` | Adres API (bez końcowego slasha). **Tylko to** zmieniasz przy porównaniu z Laravel. |
| `VUS` | `10` | Wirtualni użytkownicy (executor `constant-vus`). |
| `DURATION` | `30s` | Czas trwania scenariusza (format k6). |
| `TASK_MAX_ID` | `10000` | Górna granica losowych id tasków (show / bulk-comments). |
| `PROJECT_MAX_ID` | `50` | Górna granica losowych id projektów (create / bulk-tasks). |
| `BULK_COUNT` | `50` | Wierszy na jeden POST bulk (API max 10 000). |
| `RESULTS_DIR` | `../results` (względem `benchmark/k6`) | Gdzie lądują podsumowania JSON/CSV. W Compose: `/var/benchmark/results`. |

W Docker Compose jest jeszcze **`K6_BASE_URL`** → mapowane na `BASE_URL` w kontenerze (domyślnie `http://nginx` w sieci Compose). Przy `run-compose-k6.sh` możesz ustawić `BASE_URL` - skrypt przekaże to jako `K6_BASE_URL`.

## Uruchamianie benchmarków

### Opcja A - Docker Compose (polecane)

Serwis `k6` ma profil Compose **`benchmark`**, więc nie startuje razem z `app` / `nginx` / `mysql`.

```bash
export COMPOSE_PROFILES=benchmark   # potem wystarczy zwykłe `docker compose … run --rm k6`
docker compose -f docker-compose.yml up -d
```

Skrypt pomocniczy (ustawia `-f docker-compose.yml`, `--profile benchmark`, `uid:gid` hosta żeby `benchmark/results/` było zapisywalne, oraz przekazuje `BASE_URL` → `K6_BASE_URL` gdy ustawione):

```bash
./benchmark/run-compose-k6.sh 01-crud.js
BASE_URL=http://host.docker.internal:8081 VUS=50 DURATION=30s ./benchmark/run-compose-k6.sh 05-reports.js
```

Ręcznie, to samo:

```bash
mkdir -p benchmark/results
docker compose -f docker-compose.yml --profile benchmark run --rm \
  --user "$(id -u):$(id -g)" \
  -e VUS=50 -e DURATION=30s \
  k6 run /var/benchmark/k6/03-eager-loading.js
```

Z kontenera k6 API jest pod **`http://nginx`** (patrz `K6_BASE_URL` w `docker-compose.yml`). Z hosta - np. gdy Laravel działa lokalnie - użyj **`http://host.docker.internal:8081`** (macOS/Windows) albo IP bramy hosta na Linuxie.

### Opcja B - k6 zainstalowane na hoście

```bash
cd benchmark/k6
BASE_URL=http://localhost:8081 VUS=50 DURATION=30s k6 run 02-pagination.js
```

Wszystkie scenariusze po kolei:

```bash
cd benchmark/k6 && VUS=10 DURATION=20s ./run-all.sh
```

### Opcja C - osobny obraz k6

```bash
docker build -f benchmark/docker/Dockerfile.k6 -t task-api-k6:local benchmark
mkdir -p benchmark/results
docker run --rm --user "$(id -u):$(id -g)" \
  -v "$(pwd)/benchmark/results:/var/benchmark/results" \
  -e RESULTS_DIR=/var/benchmark/results \
  -e BASE_URL=http://host.docker.internal:8081 \
  -e VUS=20 -e DURATION=30s \
  task-api-k6:local run 04-lazy-loading.js
```

## Skrypty (scenariusze)

| Plik | Na czym się skupia |
|------|---------------------|
| `01-crud.js` | Mix **GET** listy, **GET** po id oraz cykle **POST → PUT → DELETE** (zapisy tworzą taski i je usuwają, żeby nie kasować seedów). |
| `02-pagination.js` | **GET** `/api/tasks?page=1|10|50&per_page=15`. |
| `03-eager-loading.js` | **GET** `/api/tasks?with=project,comments,tags&per_page=15&page=1`. |
| `04-lazy-loading.js` | **GET** `/api/tasks?per_page=15&page=1` (bez `with`). |
| `05-reports.js` | Rotacja **GET** `tasks-per-project`, `top-projects`, `complex-task-overview?limit=50`. |
| `06-bulk-inserts.js` | **POST** `/api/benchmark/bulk-tasks` i `/api/benchmark/bulk-comments` (powiększa bazę - przy powtarzaniu trzymaj `BULK_COUNT` na rozsądnym poziomie). |

## Wyniki (`benchmark/results/`)

Po każdym uruchomieniu `handleSummary` zapisuje:

- **`<scenario>-<timestamp>.json`** - pełny obiekt `metrics` z k6, stan `state`, rozkład statusów HTTP, metadane env i krótka notatka interpretacyjna.
- **`<scenario>-<timestamp>.csv`** - jeden wiersz podsumowania do arkusza (RPS, percentyle latency, failed rate, throughput, średnie `X-Query-Count` / `X-Response-Time-Ms`, statusy w JSON).

W konsoli nadal widać domyślne **podsumowanie tekstowe** k6 (avg / p95, checks, thresholds).

Domyślnie git ignoruje tu `*.json` i `*.csv`; `benchmark/results/.gitkeep` zostawiamy, żeby katalog był po świeżym klonie.

## Porównanie Symfony i Laravel

1. Na obu stackach uruchom **te same** fixture thesis i ten sam kontrakt API.
2. Ustaw identyczne **`VUS`**, **`DURATION`** i granice datasetu (`TASK_MAX_ID`, `PROJECT_MAX_ID`, `BULK_COUNT`).
3. Podmieniaj **`BASE_URL`** między stackami; ścieżki zostają `/api/...`.
4. Archiwizuj pliki wyników z czytelną nazwą, np. `01-crud-symfony-…json` vs `01-crud-laravel-…json`.

Przykłady env: `benchmark/comparison.env.symfony.example` i `benchmark/comparison.env.laravel.example`.

## Progi i checki

Wspólne domyślne (`benchmark/k6/lib/config.js`):

- `http_req_failed: rate < 5%`
- `http_req_duration: p(95) < 15s`

Każdy skrypt dodaje **checki** na oczekiwane statusy HTTP. Przekroczony próg = **niezerowy exit code** k6 (przydatne w CI).

## Jak czytać wyniki w pracy dyplomowej

- **Wyższe RPS** przy tym samym `VUS` zwykle oznacza krótszą średnią latencję na iterację (albo więcej wywołań HTTP na iterację - jak w cyklach zapisu w `01-crud`).
- **p95 `http_req_duration`** pokazuje ogon rozkładu pod obciążeniem; porównuj ten sam scenariusz między frameworkami.
- **Średnia `x_query_count`** - różnice w strategii ORM (N+1 vs eager loading).
- **`x_response_time_ms` vs `http_req_duration`** - nagłówek nie liczy sieci; duża luka sugeruje, że dominuje overhead sieci lub klienta.
- **Skrypty bulk** - surowy throughput insertów; dostosuj `BULK_COUNT` i czas testu, żeby tabela nie puchła nienaturalnie.

## Rozwiązywanie problemów

| Problem | Co zrobić |
|---------|-----------|
| Permission denied przy zapisie wyników z Dockera | `--user "$(id -u):$(id -g)"` (jak w `run-compose-k6.sh`) albo `chmod` na katalog wyników. |
| Przekroczony próg `http_req_failed` | API nie działa, zły `BASE_URL` albo błędy walidacji - sprawdź body odpowiedzi i logi aplikacji. |
| Dużo **404** na `GET /api/tasks/{id}` | Dostosuj `TASK_MAX_ID` do faktycznego zakresu id po częściowym seedzie. |
| `compose.yaml` nadpisuje stack | Zawsze **`-f docker-compose.yml`** albo `COMPOSE_FILE=docker-compose.yml` w `.env`. |
