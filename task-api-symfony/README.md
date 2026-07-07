# Task API (Symfony 7 + Doctrine ORM)

Aplikacja REST API przygotowana w Symfony 7 na potrzeby porównania frameworków Symfony i Laravel oraz bibliotek Doctrine ORM i Eloquent ORM w ramach pracy magisterskiej. Udostępnia te same endpointy co aplikacja `task-api-laravel`, zachowując zbliżoną strukturę odpowiedzi JSON, paginację, możliwość dołączania relacji oraz nagłówki wykorzystywane podczas testów wydajnościowych.

## Wykorzystane technologie

- Symfony 7.4, PHP 8.3+
- Doctrine ORM 3 + DBAL 4
- MySQL 8.x
- Klucze główne `BIGINT UNSIGNED` z automatyczną inkrementacją
- Docker (PHP-FPM 8.3, Nginx, MySQL 8.4)

## Uruchomienie w Dockerze

Repozytorium zawiera również drugi plik `compose.yaml` wykorzystywany przez Symfony Flex z bazą PostgreSQL. W przypadku tej aplikacji należy korzystać z pliku `docker-compose.yml`:

```bash
docker compose -f docker-compose.yml up -d
docker compose -f docker-compose.yml exec app composer install
docker compose -f docker-compose.yml exec app php bin/console doctrine:migrations:migrate --no-interaction
```

Adres API:

```
http://localhost:8081
```

Porty:

- Nginx: `8081`
- MySQL: `33061`

Domyślna konfiguracja bazy danych:

- baza: `symfony`
- użytkownik: `symfony`
- hasło: `symfony`

## Uruchomienie lokalne

Należy upewnić się, że PHP posiada rozszerzenie `pdo_mysql`, skonfigurować zmienną `DATABASE_URL` w pliku `.env`, a następnie wykonać:

```bash
composer install
php bin/console doctrine:migrations:migrate
symfony server:start
```

## Dane testowe

Projekt zawiera zestaw danych testowych o takiej samej skali jak aplikacja Laravel:

- 100 użytkowników,
- 50 projektów,
- 100 tagów,
- 10 000 zadań,
- 50 000 komentarzy,
- losowe relacje `task_tag`.

Podczas generowania danych wykorzystywane są zbiorcze zapytania SQL, co pozwala znacznie skrócić czas ich tworzenia.

```bash
php -d memory_limit=512M bin/console doctrine:fixtures:load --group=thesis --append --no-interaction
```

Usunięcie parametru `--append` spowoduje wyczyszczenie bazy danych przed załadowaniem nowych danych.

W Dockerze:

```bash
docker compose -f docker-compose.yml exec app \
  php -d memory_limit=512M bin/console doctrine:fixtures:load --group=thesis --append --no-interaction
```

## Testy wydajnościowe (k6)

Scenariusze testowe znajdują się w katalogu `benchmark/k6/`. Wyniki testów zapisywane są w katalogu `benchmark/results/` w formatach JSON oraz CSV.

Przykładowe uruchomienie:

```bash
export COMPOSE_PROFILES=benchmark
./benchmark/run-compose-k6.sh 04-lazy-loading.js
```

Uruchomienie bez Dockera:

```bash
cd benchmark/k6
BASE_URL=http://localhost:8081 VUS=50 DURATION=30s k6 run 01-crud.js
```

Szczegółowy opis dostępnych scenariuszy oraz konfiguracji znajduje się w pliku `benchmark/README.md`.

## API

Wszystkie endpointy dostępne są pod ścieżką `/api`.

Dołączanie relacji odbywa się za pomocą parametru `with`. Akceptowany jest również parametr `include` jako jego odpowiednik.

### Nagłówki testów wydajnościowych

Po przesłaniu nagłówka:

```
X-Benchmark-Metrics: 1
```

w odpowiedzi zwracane są dodatkowe informacje:

- `X-Query-Count` – liczba wykonanych zapytań SQL,
- `X-Response-Time-Ms` – czas obsługi żądania w milisekundach.

### Paginacja

Dostępne parametry:

- `page`
- `per_page` (domyślnie 15, maksymalnie 100)

Struktura odpowiedzi jest zgodna z aplikacją Laravel i zawiera sekcje `data`, `links` oraz `meta`.

### Filtrowanie

| Zasób | Parametry |
|-------|-----------|
| Projects | `status`, `user_id` |
| Tasks | `status`, `priority`, `project_id` |
| Comments | `task_id` |

### Endpointy

- `GET/POST /api/projects`, `GET/PUT/DELETE /api/projects/{id}`
- `GET/POST /api/tasks`, `GET/PUT/DELETE /api/tasks/{id}`
- `GET /api/comments`, `POST /api/tasks/{id}/comments`, `GET/PUT/DELETE /api/comments/{id}`
- `GET/POST /api/tags`, `GET/PUT/DELETE /api/tags/{id}`
- `GET /api/reports/tasks-per-project`
- `GET /api/reports/top-projects`
- `GET /api/reports/complex-task-overview`
- `POST /api/benchmark/bulk-tasks`
- `POST /api/benchmark/bulk-comments`

## Struktura projektu

- `src/Entity` – encje Doctrine,
- `src/Repository` – repozytoria oraz zapytania do bazy danych,
- `src/Service` – logika biznesowa aplikacji,
- `src/Controller/Api` – kontrolery obsługujące endpointy API,
- `src/Dto` – obiekty DTO wykorzystywane w żądaniach,
- `src/Api` – obsługa parametru `with` oraz serializacja odpowiedzi,
- `src/Benchmark` i `src/EventSubscriber` – obsługa metryk wykorzystywanych podczas testów wydajnościowych,
- `migrations` – migracje bazy danych,
- `docker` – konfiguracja środowiska Docker.

## Uwagi

- Struktura odpowiedzi JSON została możliwie wiernie odwzorowana względem aplikacji Laravel.
- Obsługiwane relacje ładowane są w sposób ograniczający problem N+1 zapytań.
- Endpointy służące do testów wydajnościowych wykorzystują bezpośrednie zapytania SQL zamiast encji Doctrine, dzięki czemu możliwe jest sprawiedliwe porównanie z implementacją Laravel.