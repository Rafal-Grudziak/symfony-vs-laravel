# Task Management API (Laravel 12)

Aplikacja REST API przygotowana w Laravelu 12 na potrzeby porównania frameworków Laravel i Symfony oraz bibliotek Eloquent ORM i Doctrine ORM w ramach pracy magisterskiej. Projekt przedstawia prosty system zarządzania zadaniami z użytkownikami, projektami, zadaniami, komentarzami i tagami. Udostępnia paginację, filtrowanie, dołączanie relacji oraz mechanizmy wykorzystywane podczas testów wydajnościowych.

## Wykorzystane technologie

- Laravel 12
- PHP 8.3+
- MySQL 8.4
- Eloquent ORM, Form Requests, API Resources
- Docker (PHP-FPM, Nginx, MySQL)

## Uruchomienie w Dockerze

1. Skopiuj plik konfiguracyjny środowiska i wygeneruj klucz aplikacji:

```bash
cp .env.example .env
php artisan key:generate
```

Podczas uruchamiania przez Docker Compose ustawienia bazy danych zostaną pobrane z pliku `docker-compose.yml`. W pliku `.env` musi znajdować się jedynie poprawny `APP_KEY`.

2. Uruchom kontenery:

```bash
docker compose up -d --build
```

3. Zainstaluj zależności i przygotuj bazę danych:

```bash
docker compose exec app composer install --no-interaction
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app php artisan migrate:fresh --seed --force
```

Pierwsze uruchomienie może potrwać kilka minut ze względu na generowanie dużej liczby danych testowych.

4. API będzie dostępne pod adresem:

```
http://localhost:8080
```

Przykładowe zapytanie:

```bash
curl -s "http://localhost:8080/api/tasks?per_page=5" | jq
```

MySQL jest dostępny na porcie `33060`, dzięki czemu można połączyć się z bazą za pomocą zewnętrznych narzędzi.

## Uruchomienie lokalne

Wymagane są PHP 8.3+, Composer oraz MySQL. Po skonfigurowaniu zmiennych `DB_*` w pliku `.env` wykonaj:

```bash
composer install
php artisan migrate:fresh --seed
php artisan serve
```

## API

Wszystkie endpointy dostępne są pod prefiksem `/api` i zwracają odpowiedzi w formacie JSON. Błędy walidacji wykorzystują standardowy format odpowiedzi Laravel.

### Projekty

Dostępne endpointy:

| Metoda | Ścieżka |
|--------|---------|
| GET | `/api/projects` |
| POST | `/api/projects` |
| GET | `/api/projects/{id}` |
| PUT/PATCH | `/api/projects/{id}` |
| DELETE | `/api/projects/{id}` |

Parametry:

- `per_page` (1–100, domyślnie 15)
- `status`
- `user_id`
- `with` (`user`, `tasks`)

Przykład:

```http
GET /api/projects?with=user,tasks&per_page=10
```

lub

```http
GET /api/projects?with[]=user&with[]=tasks
```

### Zadania

| Metoda | Ścieżka |
|--------|---------|
| GET | `/api/tasks` |
| POST | `/api/tasks` |
| GET | `/api/tasks/{id}` |
| PUT/PATCH | `/api/tasks/{id}` |
| DELETE | `/api/tasks/{id}` |

Parametry:

- `per_page`
- `project_id`
- `status`
- `priority`
- `with` (`project`, `comments`, `tags`)

Podczas tworzenia lub edycji zadania można przekazać pole `tag_ids`, które synchronizuje relację wiele-do-wielu.

Przykład:

```http
GET /api/tasks?status=todo&with=project,tags&per_page=20
```

### Komentarze

| Metoda | Ścieżka |
|--------|---------|
| GET | `/api/comments` |
| GET | `/api/comments/{id}` |
| PUT/PATCH | `/api/comments/{id}` |
| DELETE | `/api/comments/{id}` |
| POST | `/api/tasks/{id}/comments` |

Parametry:

- `per_page`
- `task_id`
- `with` (`task`)

### Tagi

| Metoda | Ścieżka |
|--------|---------|
| GET | `/api/tags` |
| POST | `/api/tags` |
| GET | `/api/tags/{id}` |
| PUT/PATCH | `/api/tags/{id}` |
| DELETE | `/api/tags/{id}` |

Obsługiwany parametr:

- `with=tasks`

### Endpointy wykorzystywane podczas testów wydajnościowych

| Metoda | Ścieżka |
|--------|---------|
| POST | `/api/benchmark/bulk-tasks` |
| POST | `/api/benchmark/bulk-comments` |

### Nagłówki metryk

Po przesłaniu nagłówka:

```
X-Benchmark-Metrics: 1
```

w odpowiedzi zwracane są dodatkowe informacje:

- `X-Query-Count` – liczba wykonanych zapytań SQL,
- `X-Response-Time-Ms` – czas obsługi żądania w milisekundach.

## Model danych

- User → wiele Project
- Project → należy do User i posiada wiele Task
- Task → należy do Project, posiada wiele Comment oraz wiele Tag
- Comment → należy do Task
- Tag → posiada relację wiele-do-wielu z Task

Wszystkie tabele wykorzystują klucze główne typu `BIGINT UNSIGNED`.

## Dane testowe

Uruchomienie polecenia:

```bash
php artisan migrate:fresh --seed
```

tworzy:

- 100 użytkowników,
- 50 projektów,
- 100 tagów,
- 10 000 zadań,
- 50 000 komentarzy,
- losowe relacje `task_tag`.

## Struktura projektu

- `app/Http/Controllers/Api` – kontrolery REST,
- `app/Http/Requests` – walidacja danych,
- `app/Http/Resources` – formatowanie odpowiedzi JSON,
- `app/Services` – logika biznesowa,
- `app/Http/Concerns` – obsługa parametru `with`,
- `database/migrations` – migracje bazy danych,
- `database/factories` i `database/seeders` – generowanie danych testowych,
- `docker` – konfiguracja środowiska Docker,
- `docker-compose.yml` – konfiguracja kontenerów.
