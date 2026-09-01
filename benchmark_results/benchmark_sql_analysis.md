# SQL comparison

Generated: `2026-08-25 19:10:58 UTC`

Źródło: rzeczywiste zapytania z `sql_benchmark.log` (nagłówki `X-Benchmark-Metrics: 1`, `X-Benchmark-Sql-Log: 1`).

Scenariusze odpowiadają `benchmark_read_operations.sh` oraz `benchmark_write_operations.sh` (po jednym requestcie na scenariusz).

- Laravel: `http://localhost:8080` → `task-api-laravel/storage/logs/sql_benchmark.log`
- Symfony: `http://localhost:8081` → `task-api-symfony/var/log/sql_benchmark.log`

Operacje pomocnicze POST/DELETE (przygotowanie / sprzątanie) nie są uwzględnione w wynikach scenariuszy.

## list_per_page_15 — GET /api/tasks?per_page=15

### Laravel

- Method: `GET`
- URI: `/api/tasks?per_page=15`
- HTTP status: `200`
- Query count (`X-Query-Count`): **2**
- Request id (`X-Benchmark-Request-Id`): `43db88ab26f1d985`

1. SQL:

```sql
select count(*) as aggregate from `tasks`
```

Bindings:

```json
[]
```

2. SQL:

```sql
select * from `tasks` order by `id` desc limit 15 offset 0
```

Bindings:

```json
[]
```

### Symfony

- Method: `GET`
- URI: `/api/tasks?per_page=15`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `deccc528cbd8ab96`

1. SQL:

```sql
SELECT DISTINCT id_0 FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7 FROM tasks t0_) dctrn_result_inner ORDER BY id_0 DESC) dctrn_result LIMIT 15
```

Parameters:

```json
(brak)
```

2. SQL:

```sql
SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, t0_.project_id AS project_id_8 FROM tasks t0_ WHERE t0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ORDER BY t0_.id DESC
```

Parameters:

```json
{
  "1": 9999,
  "2": 9998,
  "3": 9997,
  "4": 9996,
  "5": 9995,
  "6": 9994,
  "7": 9993,
  "8": 9992,
  "9": 9991,
  "10": 9990,
  "11": 9989,
  "12": 9988,
  "13": 9987,
  "14": 9986,
  "15": 9985
}
```

3. SQL:

```sql
SELECT COUNT(*) AS dctrn_count FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7 FROM tasks t0_ ORDER BY t0_.id DESC) dctrn_result) dctrn_table
```

Parameters:

```json
(brak)
```

## list_per_page_100 — GET /api/tasks?per_page=100

### Laravel

- Method: `GET`
- URI: `/api/tasks?per_page=100`
- HTTP status: `200`
- Query count (`X-Query-Count`): **2**
- Request id (`X-Benchmark-Request-Id`): `d735ff1f06c5a28c`

1. SQL:

```sql
select count(*) as aggregate from `tasks`
```

Bindings:

```json
[]
```

2. SQL:

```sql
select * from `tasks` order by `id` desc limit 100 offset 0
```

Bindings:

```json
[]
```

### Symfony

- Method: `GET`
- URI: `/api/tasks?per_page=100`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `9ed659a5a5bcfc89`

1. SQL:

```sql
SELECT DISTINCT id_0 FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7 FROM tasks t0_) dctrn_result_inner ORDER BY id_0 DESC) dctrn_result LIMIT 100
```

Parameters:

```json
(brak)
```

2. SQL:

```sql
SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, t0_.project_id AS project_id_8 FROM tasks t0_ WHERE t0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ORDER BY t0_.id DESC
```

Parameters:

```json
{
  "1": 9999,
  "2": 9998,
  "3": 9997,
  "4": 9996,
  "5": 9995,
  "6": 9994,
  "7": 9993,
  "8": 9992,
  "9": 9991,
  "10": 9990,
  "11": 9989,
  "12": 9988,
  "13": 9987,
  "14": 9986,
  "15": 9985,
  "16": 9984,
  "17": 9983,
  "18": 9982,
  "19": 9981,
  "20": 9980,
  "21": 9979,
  "22": 9978,
  "23": 9977,
  "24": 9976,
  "25": 9975,
  "26": 9974,
  "27": 9973,
  "28": 9972,
  "29": 9971,
  "30": 9970,
  "31": 9969,
  "32": 9968,
  "33": 9967,
  "34": 9966,
  "35": 9965,
  "36": 9964,
  "37": 9963,
  "38": 9962,
  "39": 9961,
  "40": 9960,
  "41": 9959,
  "42": 9958,
  "43": 9957,
  "44": 9956,
  "45": 9955,
  "46": 9954,
  "47": 9953,
  "48": 9952,
  "49": 9951,
  "50": 9950,
  "51": 9949,
  "52": 9948,
  "53": 9947,
  "54": 9946,
  "55": 9945,
  "56": 9944,
  "57": 9943,
  "58": 9942,
  "59": 9941,
  "60": 9940,
  "61": 9939,
  "62": 9938,
  "63": 9937,
  "64": 9936,
  "65": 9935,
  "66": 9934,
  "67": 9933,
  "68": 9932,
  "69": 9931,
  "70": 9930,
  "71": 9929,
  "72": 9928,
  "73": 9927,
  "74": 9926,
  "75": 9925,
  "76": 9924,
  "77": 9923,
  "78": 9922,
  "79": 9921,
  "80": 9920,
  "81": 9919,
  "82": 9918,
  "83": 9917,
  "84": 9916,
  "85": 9915,
  "86": 9914,
  "87": 9913,
  "88": 9912,
  "89": 9911,
  "90": 9910,
  "91": 9909,
  "92": 9908,
  "93": 9907,
  "94": 9906,
  "95": 9905,
  "96": 9904,
  "97": 9903,
  "98": 9902,
  "99": 9901,
  "100": 9900
}
```

3. SQL:

```sql
SELECT COUNT(*) AS dctrn_count FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7 FROM tasks t0_ ORDER BY t0_.id DESC) dctrn_result) dctrn_table
```

Parameters:

```json
(brak)
```

## single_task — GET /api/tasks/{id}

### Laravel

- Method: `GET`
- URI: `/api/tasks/9999`
- HTTP status: `200`
- Query count (`X-Query-Count`): **1**
- Request id (`X-Benchmark-Request-Id`): `1cf335122235d91c`

1. SQL:

```sql
select * from `tasks` where `id` = ? limit 1
```

Bindings:

```json
[
  "9999"
]
```

### Symfony

- Method: `GET`
- URI: `/api/tasks/9999`
- HTTP status: `200`
- Query count (`X-Query-Count`): **1**
- Request id (`X-Benchmark-Request-Id`): `6d2e7d6bd19d2484`

1. SQL:

```sql
SELECT t0.id AS id_1, t0.title AS title_2, t0.description AS description_3, t0.status AS status_4, t0.priority AS priority_5, t0.due_date AS due_date_6, t0.created_at AS created_at_7, t0.updated_at AS updated_at_8, t0.project_id AS project_id_9 FROM tasks t0 WHERE t0.id = ?
```

Parameters:

```json
{
  "1": "9999"
}
```

## list_with_project — GET …&with=project

### Laravel

- Method: `GET`
- URI: `/api/tasks?per_page=15&with=project`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `9459731c37917406`

1. SQL:

```sql
select count(*) as aggregate from `tasks`
```

Bindings:

```json
[]
```

2. SQL:

```sql
select * from `tasks` order by `id` desc limit 15 offset 0
```

Bindings:

```json
[]
```

3. SQL:

```sql
select * from `projects` where `projects`.`id` in (50)
```

Bindings:

```json
[]
```

### Symfony

- Method: `GET`
- URI: `/api/tasks?per_page=15&with=project`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `d5fadf1dcb129a98`

1. SQL:

```sql
SELECT DISTINCT id_0 FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, p1_.id AS id_8, p1_.name AS name_9, p1_.description AS description_10, p1_.status AS status_11, p1_.created_at AS created_at_12, p1_.updated_at AS updated_at_13 FROM tasks t0_ LEFT JOIN projects p1_ ON t0_.project_id = p1_.id) dctrn_result_inner ORDER BY id_0 DESC) dctrn_result LIMIT 15
```

Parameters:

```json
(brak)
```

2. SQL:

```sql
SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, p1_.id AS id_8, p1_.name AS name_9, p1_.description AS description_10, p1_.status AS status_11, p1_.created_at AS created_at_12, p1_.updated_at AS updated_at_13, t0_.project_id AS project_id_14, p1_.user_id AS user_id_15 FROM tasks t0_ LEFT JOIN projects p1_ ON t0_.project_id = p1_.id WHERE t0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ORDER BY t0_.id DESC
```

Parameters:

```json
{
  "1": 9999,
  "2": 9998,
  "3": 9997,
  "4": 9996,
  "5": 9995,
  "6": 9994,
  "7": 9993,
  "8": 9992,
  "9": 9991,
  "10": 9990,
  "11": 9989,
  "12": 9988,
  "13": 9987,
  "14": 9986,
  "15": 9985
}
```

3. SQL:

```sql
SELECT COUNT(*) AS dctrn_count FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, p1_.id AS id_8, p1_.name AS name_9, p1_.description AS description_10, p1_.status AS status_11, p1_.created_at AS created_at_12, p1_.updated_at AS updated_at_13 FROM tasks t0_ LEFT JOIN projects p1_ ON t0_.project_id = p1_.id ORDER BY t0_.id DESC) dctrn_result) dctrn_table
```

Parameters:

```json
(brak)
```

## list_with_comments — GET …&with=comments

### Laravel

- Method: `GET`
- URI: `/api/tasks?per_page=15&with=comments`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `240ea6f4c26eb9da`

1. SQL:

```sql
select count(*) as aggregate from `tasks`
```

Bindings:

```json
[]
```

2. SQL:

```sql
select * from `tasks` order by `id` desc limit 15 offset 0
```

Bindings:

```json
[]
```

3. SQL:

```sql
select * from `comments` where `comments`.`task_id` in (9985, 9986, 9987, 9988, 9989, 9990, 9991, 9992, 9993, 9994, 9995, 9996, 9997, 9998, 9999)
```

Bindings:

```json
[]
```

### Symfony

- Method: `GET`
- URI: `/api/tasks?per_page=15&with=comments`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `30d2111383d6013c`

1. SQL:

```sql
SELECT DISTINCT id_0 FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, c1_.id AS id_8, c1_.content AS content_9, c1_.created_at AS created_at_10, c1_.updated_at AS updated_at_11 FROM tasks t0_ LEFT JOIN comments c1_ ON t0_.id = c1_.task_id) dctrn_result_inner ORDER BY id_0 DESC) dctrn_result LIMIT 15
```

Parameters:

```json
(brak)
```

2. SQL:

```sql
SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, c1_.id AS id_8, c1_.content AS content_9, c1_.created_at AS created_at_10, c1_.updated_at AS updated_at_11, t0_.project_id AS project_id_12, c1_.task_id AS task_id_13 FROM tasks t0_ LEFT JOIN comments c1_ ON t0_.id = c1_.task_id WHERE t0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ORDER BY t0_.id DESC
```

Parameters:

```json
{
  "1": 9999,
  "2": 9998,
  "3": 9997,
  "4": 9996,
  "5": 9995,
  "6": 9994,
  "7": 9993,
  "8": 9992,
  "9": 9991,
  "10": 9990,
  "11": 9989,
  "12": 9988,
  "13": 9987,
  "14": 9986,
  "15": 9985
}
```

3. SQL:

```sql
SELECT COUNT(*) AS dctrn_count FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, c1_.id AS id_8, c1_.content AS content_9, c1_.created_at AS created_at_10, c1_.updated_at AS updated_at_11 FROM tasks t0_ LEFT JOIN comments c1_ ON t0_.id = c1_.task_id ORDER BY t0_.id DESC) dctrn_result) dctrn_table
```

Parameters:

```json
(brak)
```

## list_with_tags — GET …&with=tags

### Laravel

- Method: `GET`
- URI: `/api/tasks?per_page=15&with=tags`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `8049a0e2cec47b71`

1. SQL:

```sql
select count(*) as aggregate from `tasks`
```

Bindings:

```json
[]
```

2. SQL:

```sql
select * from `tasks` order by `id` desc limit 15 offset 0
```

Bindings:

```json
[]
```

3. SQL:

```sql
select `tags`.*, `task_tag`.`task_id` as `pivot_task_id`, `task_tag`.`tag_id` as `pivot_tag_id`, `task_tag`.`created_at` as `pivot_created_at`, `task_tag`.`updated_at` as `pivot_updated_at` from `tags` inner join `task_tag` on `tags`.`id` = `task_tag`.`tag_id` where `task_tag`.`task_id` in (9985, 9986, 9987, 9988, 9989, 9990, 9991, 9992, 9993, 9994, 9995, 9996, 9997, 9998, 9999)
```

Bindings:

```json
[]
```

### Symfony

- Method: `GET`
- URI: `/api/tasks?per_page=15&with=tags`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `cc1ee36dfbb76cbf`

1. SQL:

```sql
SELECT DISTINCT id_0 FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, t1_.id AS id_8, t1_.created_at AS created_at_9, t1_.updated_at AS updated_at_10, t2_.id AS id_11, t2_.name AS name_12, t2_.color AS color_13, t2_.created_at AS created_at_14, t2_.updated_at AS updated_at_15 FROM tasks t0_ LEFT JOIN task_tag t1_ ON t0_.id = t1_.task_id LEFT JOIN tags t2_ ON t1_.tag_id = t2_.id) dctrn_result_inner ORDER BY id_0 DESC) dctrn_result LIMIT 15
```

Parameters:

```json
(brak)
```

2. SQL:

```sql
SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, t1_.id AS id_8, t1_.created_at AS created_at_9, t1_.updated_at AS updated_at_10, t2_.id AS id_11, t2_.name AS name_12, t2_.color AS color_13, t2_.created_at AS created_at_14, t2_.updated_at AS updated_at_15, t0_.project_id AS project_id_16, t1_.task_id AS task_id_17, t1_.tag_id AS tag_id_18 FROM tasks t0_ LEFT JOIN task_tag t1_ ON t0_.id = t1_.task_id LEFT JOIN tags t2_ ON t1_.tag_id = t2_.id WHERE t0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ORDER BY t0_.id DESC
```

Parameters:

```json
{
  "1": 9999,
  "2": 9998,
  "3": 9997,
  "4": 9996,
  "5": 9995,
  "6": 9994,
  "7": 9993,
  "8": 9992,
  "9": 9991,
  "10": 9990,
  "11": 9989,
  "12": 9988,
  "13": 9987,
  "14": 9986,
  "15": 9985
}
```

3. SQL:

```sql
SELECT COUNT(*) AS dctrn_count FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, t1_.id AS id_8, t1_.created_at AS created_at_9, t1_.updated_at AS updated_at_10, t2_.id AS id_11, t2_.name AS name_12, t2_.color AS color_13, t2_.created_at AS created_at_14, t2_.updated_at AS updated_at_15 FROM tasks t0_ LEFT JOIN task_tag t1_ ON t0_.id = t1_.task_id LEFT JOIN tags t2_ ON t1_.tag_id = t2_.id ORDER BY t0_.id DESC) dctrn_result) dctrn_table
```

Parameters:

```json
(brak)
```

## list_with_all — GET …&with=project,comments,tags

### Laravel

- Method: `GET`
- URI: `/api/tasks?per_page=15&with=project,comments,tags`
- HTTP status: `200`
- Query count (`X-Query-Count`): **5**
- Request id (`X-Benchmark-Request-Id`): `4f4740096b7038d5`

1. SQL:

```sql
select count(*) as aggregate from `tasks`
```

Bindings:

```json
[]
```

2. SQL:

```sql
select * from `tasks` order by `id` desc limit 15 offset 0
```

Bindings:

```json
[]
```

3. SQL:

```sql
select * from `projects` where `projects`.`id` in (50)
```

Bindings:

```json
[]
```

4. SQL:

```sql
select * from `comments` where `comments`.`task_id` in (9985, 9986, 9987, 9988, 9989, 9990, 9991, 9992, 9993, 9994, 9995, 9996, 9997, 9998, 9999)
```

Bindings:

```json
[]
```

5. SQL:

```sql
select `tags`.*, `task_tag`.`task_id` as `pivot_task_id`, `task_tag`.`tag_id` as `pivot_tag_id`, `task_tag`.`created_at` as `pivot_created_at`, `task_tag`.`updated_at` as `pivot_updated_at` from `tags` inner join `task_tag` on `tags`.`id` = `task_tag`.`tag_id` where `task_tag`.`task_id` in (9985, 9986, 9987, 9988, 9989, 9990, 9991, 9992, 9993, 9994, 9995, 9996, 9997, 9998, 9999)
```

Bindings:

```json
[]
```

### Symfony

- Method: `GET`
- URI: `/api/tasks?per_page=15&with=project,comments,tags`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `b3ec68e24010fbee`

1. SQL:

```sql
SELECT DISTINCT id_0 FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, p1_.id AS id_8, p1_.name AS name_9, p1_.description AS description_10, p1_.status AS status_11, p1_.created_at AS created_at_12, p1_.updated_at AS updated_at_13, c2_.id AS id_14, c2_.content AS content_15, c2_.created_at AS created_at_16, c2_.updated_at AS updated_at_17, t3_.id AS id_18, t3_.created_at AS created_at_19, t3_.updated_at AS updated_at_20, t4_.id AS id_21, t4_.name AS name_22, t4_.color AS color_23, t4_.created_at AS created_at_24, t4_.updated_at AS updated_at_25 FROM tasks t0_ LEFT JOIN projects p1_ ON t0_.project_id = p1_.id LEFT JOIN comments c2_ ON t0_.id = c2_.task_id LEFT JOIN task_tag t3_ ON t0_.id = t3_.task_id LEFT JOIN tags t4_ ON t3_.tag_id = t4_.id) dctrn_result_inner ORDER BY id_0 DESC) dctrn_result LIMIT 15
```

Parameters:

```json
(brak)
```

2. SQL:

```sql
SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, p1_.id AS id_8, p1_.name AS name_9, p1_.description AS description_10, p1_.status AS status_11, p1_.created_at AS created_at_12, p1_.updated_at AS updated_at_13, c2_.id AS id_14, c2_.content AS content_15, c2_.created_at AS created_at_16, c2_.updated_at AS updated_at_17, t3_.id AS id_18, t3_.created_at AS created_at_19, t3_.updated_at AS updated_at_20, t4_.id AS id_21, t4_.name AS name_22, t4_.color AS color_23, t4_.created_at AS created_at_24, t4_.updated_at AS updated_at_25, t0_.project_id AS project_id_26, p1_.user_id AS user_id_27, c2_.task_id AS task_id_28, t3_.task_id AS task_id_29, t3_.tag_id AS tag_id_30 FROM tasks t0_ LEFT JOIN projects p1_ ON t0_.project_id = p1_.id LEFT JOIN comments c2_ ON t0_.id = c2_.task_id LEFT JOIN task_tag t3_ ON t0_.id = t3_.task_id LEFT JOIN tags t4_ ON t3_.tag_id = t4_.id WHERE t0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ORDER BY t0_.id DESC
```

Parameters:

```json
{
  "1": 9999,
  "2": 9998,
  "3": 9997,
  "4": 9996,
  "5": 9995,
  "6": 9994,
  "7": 9993,
  "8": 9992,
  "9": 9991,
  "10": 9990,
  "11": 9989,
  "12": 9988,
  "13": 9987,
  "14": 9986,
  "15": 9985
}
```

3. SQL:

```sql
SELECT COUNT(*) AS dctrn_count FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, p1_.id AS id_8, p1_.name AS name_9, p1_.description AS description_10, p1_.status AS status_11, p1_.created_at AS created_at_12, p1_.updated_at AS updated_at_13, c2_.id AS id_14, c2_.content AS content_15, c2_.created_at AS created_at_16, c2_.updated_at AS updated_at_17, t3_.id AS id_18, t3_.created_at AS created_at_19, t3_.updated_at AS updated_at_20, t4_.id AS id_21, t4_.name AS name_22, t4_.color AS color_23, t4_.created_at AS created_at_24, t4_.updated_at AS updated_at_25 FROM tasks t0_ LEFT JOIN projects p1_ ON t0_.project_id = p1_.id LEFT JOIN comments c2_ ON t0_.id = c2_.task_id LEFT JOIN task_tag t3_ ON t0_.id = t3_.task_id LEFT JOIN tags t4_ ON t3_.tag_id = t4_.id ORDER BY t0_.id DESC) dctrn_result) dctrn_table
```

Parameters:

```json
(brak)
```

## list_100_with_all — GET …?per_page=100&with=project,comments,tags

### Laravel

- Method: `GET`
- URI: `/api/tasks?per_page=100&with=project,comments,tags`
- HTTP status: `200`
- Query count (`X-Query-Count`): **5**
- Request id (`X-Benchmark-Request-Id`): `7223040312161510`

1. SQL:

```sql
select count(*) as aggregate from `tasks`
```

Bindings:

```json
[]
```

2. SQL:

```sql
select * from `tasks` order by `id` desc limit 100 offset 0
```

Bindings:

```json
[]
```

3. SQL:

```sql
select * from `projects` where `projects`.`id` in (50)
```

Bindings:

```json
[]
```

4. SQL:

```sql
select * from `comments` where `comments`.`task_id` in (9900, 9901, 9902, 9903, 9904, 9905, 9906, 9907, 9908, 9909, 9910, 9911, 9912, 9913, 9914, 9915, 9916, 9917, 9918, 9919, 9920, 9921, 9922, 9923, 9924, 9925, 9926, 9927, 9928, 9929, 9930, 9931, 9932, 9933, 9934, 9935, 9936, 9937, 9938, 9939, 9940, 9941, 9942, 9943, 9944, 9945, 9946, 9947, 9948, 9949, 9950, 9951, 9952, 9953, 9954, 9955, 9956, 9957, 9958, 9959, 9960, 9961, 9962, 9963, 9964, 9965, 9966, 9967, 9968, 9969, 9970, 9971, 9972, 9973, 9974, 9975, 9976, 9977, 9978, 9979, 9980, 9981, 9982, 9983, 9984, 9985, 9986, 9987, 9988, 9989, 9990, 9991, 9992, 9993, 9994, 9995, 9996, 9997, 9998, 9999)
```

Bindings:

```json
[]
```

5. SQL:

```sql
select `tags`.*, `task_tag`.`task_id` as `pivot_task_id`, `task_tag`.`tag_id` as `pivot_tag_id`, `task_tag`.`created_at` as `pivot_created_at`, `task_tag`.`updated_at` as `pivot_updated_at` from `tags` inner join `task_tag` on `tags`.`id` = `task_tag`.`tag_id` where `task_tag`.`task_id` in (9900, 9901, 9902, 9903, 9904, 9905, 9906, 9907, 9908, 9909, 9910, 9911, 9912, 9913, 9914, 9915, 9916, 9917, 9918, 9919, 9920, 9921, 9922, 9923, 9924, 9925, 9926, 9927, 9928, 9929, 9930, 9931, 9932, 9933, 9934, 9935, 9936, 9937, 9938, 9939, 9940, 9941, 9942, 9943, 9944, 9945, 9946, 9947, 9948, 9949, 9950, 9951, 9952, 9953, 9954, 9955, 9956, 9957, 9958, 9959, 9960, 9961, 9962, 9963, 9964, 9965, 9966, 9967, 9968, 9969, 9970, 9971, 9972, 9973, 9974, 9975, 9976, 9977, 9978, 9979, 9980, 9981, 9982, 9983, 9984, 9985, 9986, 9987, 9988, 9989, 9990, 9991, 9992, 9993, 9994, 9995, 9996, 9997, 9998, 9999)
```

Bindings:

```json
[]
```

### Symfony

- Method: `GET`
- URI: `/api/tasks?per_page=100&with=project,comments,tags`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `1cc2a5b639d1a380`

1. SQL:

```sql
SELECT DISTINCT id_0 FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, p1_.id AS id_8, p1_.name AS name_9, p1_.description AS description_10, p1_.status AS status_11, p1_.created_at AS created_at_12, p1_.updated_at AS updated_at_13, c2_.id AS id_14, c2_.content AS content_15, c2_.created_at AS created_at_16, c2_.updated_at AS updated_at_17, t3_.id AS id_18, t3_.created_at AS created_at_19, t3_.updated_at AS updated_at_20, t4_.id AS id_21, t4_.name AS name_22, t4_.color AS color_23, t4_.created_at AS created_at_24, t4_.updated_at AS updated_at_25 FROM tasks t0_ LEFT JOIN projects p1_ ON t0_.project_id = p1_.id LEFT JOIN comments c2_ ON t0_.id = c2_.task_id LEFT JOIN task_tag t3_ ON t0_.id = t3_.task_id LEFT JOIN tags t4_ ON t3_.tag_id = t4_.id) dctrn_result_inner ORDER BY id_0 DESC) dctrn_result LIMIT 100
```

Parameters:

```json
(brak)
```

2. SQL:

```sql
SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, p1_.id AS id_8, p1_.name AS name_9, p1_.description AS description_10, p1_.status AS status_11, p1_.created_at AS created_at_12, p1_.updated_at AS updated_at_13, c2_.id AS id_14, c2_.content AS content_15, c2_.created_at AS created_at_16, c2_.updated_at AS updated_at_17, t3_.id AS id_18, t3_.created_at AS created_at_19, t3_.updated_at AS updated_at_20, t4_.id AS id_21, t4_.name AS name_22, t4_.color AS color_23, t4_.created_at AS created_at_24, t4_.updated_at AS updated_at_25, t0_.project_id AS project_id_26, p1_.user_id AS user_id_27, c2_.task_id AS task_id_28, t3_.task_id AS task_id_29, t3_.tag_id AS tag_id_30 FROM tasks t0_ LEFT JOIN projects p1_ ON t0_.project_id = p1_.id LEFT JOIN comments c2_ ON t0_.id = c2_.task_id LEFT JOIN task_tag t3_ ON t0_.id = t3_.task_id LEFT JOIN tags t4_ ON t3_.tag_id = t4_.id WHERE t0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ORDER BY t0_.id DESC
```

Parameters:

```json
{
  "1": 9999,
  "2": 9998,
  "3": 9997,
  "4": 9996,
  "5": 9995,
  "6": 9994,
  "7": 9993,
  "8": 9992,
  "9": 9991,
  "10": 9990,
  "11": 9989,
  "12": 9988,
  "13": 9987,
  "14": 9986,
  "15": 9985,
  "16": 9984,
  "17": 9983,
  "18": 9982,
  "19": 9981,
  "20": 9980,
  "21": 9979,
  "22": 9978,
  "23": 9977,
  "24": 9976,
  "25": 9975,
  "26": 9974,
  "27": 9973,
  "28": 9972,
  "29": 9971,
  "30": 9970,
  "31": 9969,
  "32": 9968,
  "33": 9967,
  "34": 9966,
  "35": 9965,
  "36": 9964,
  "37": 9963,
  "38": 9962,
  "39": 9961,
  "40": 9960,
  "41": 9959,
  "42": 9958,
  "43": 9957,
  "44": 9956,
  "45": 9955,
  "46": 9954,
  "47": 9953,
  "48": 9952,
  "49": 9951,
  "50": 9950,
  "51": 9949,
  "52": 9948,
  "53": 9947,
  "54": 9946,
  "55": 9945,
  "56": 9944,
  "57": 9943,
  "58": 9942,
  "59": 9941,
  "60": 9940,
  "61": 9939,
  "62": 9938,
  "63": 9937,
  "64": 9936,
  "65": 9935,
  "66": 9934,
  "67": 9933,
  "68": 9932,
  "69": 9931,
  "70": 9930,
  "71": 9929,
  "72": 9928,
  "73": 9927,
  "74": 9926,
  "75": 9925,
  "76": 9924,
  "77": 9923,
  "78": 9922,
  "79": 9921,
  "80": 9920,
  "81": 9919,
  "82": 9918,
  "83": 9917,
  "84": 9916,
  "85": 9915,
  "86": 9914,
  "87": 9913,
  "88": 9912,
  "89": 9911,
  "90": 9910,
  "91": 9909,
  "92": 9908,
  "93": 9907,
  "94": 9906,
  "95": 9905,
  "96": 9904,
  "97": 9903,
  "98": 9902,
  "99": 9901,
  "100": 9900
}
```

3. SQL:

```sql
SELECT COUNT(*) AS dctrn_count FROM (SELECT DISTINCT id_0 FROM (SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, p1_.id AS id_8, p1_.name AS name_9, p1_.description AS description_10, p1_.status AS status_11, p1_.created_at AS created_at_12, p1_.updated_at AS updated_at_13, c2_.id AS id_14, c2_.content AS content_15, c2_.created_at AS created_at_16, c2_.updated_at AS updated_at_17, t3_.id AS id_18, t3_.created_at AS created_at_19, t3_.updated_at AS updated_at_20, t4_.id AS id_21, t4_.name AS name_22, t4_.color AS color_23, t4_.created_at AS created_at_24, t4_.updated_at AS updated_at_25 FROM tasks t0_ LEFT JOIN projects p1_ ON t0_.project_id = p1_.id LEFT JOIN comments c2_ ON t0_.id = c2_.task_id LEFT JOIN task_tag t3_ ON t0_.id = t3_.task_id LEFT JOIN tags t4_ ON t3_.tag_id = t4_.id ORDER BY t0_.id DESC) dctrn_result) dctrn_table
```

Parameters:

```json
(brak)
```

## single_with_all — GET /api/tasks/{id}?with=project,comments,tags

### Laravel

- Method: `GET`
- URI: `/api/tasks/9999?with=project,comments,tags`
- HTTP status: `200`
- Query count (`X-Query-Count`): **5**
- Request id (`X-Benchmark-Request-Id`): `02ebd2d57752d414`

1. SQL:

```sql
select * from `tasks` where `id` = ? limit 1
```

Bindings:

```json
[
  "9999"
]
```

2. SQL:

```sql
select * from `tasks` where `tasks`.`id` = ? limit 1
```

Bindings:

```json
[
  9999
]
```

3. SQL:

```sql
select * from `projects` where `projects`.`id` in (50)
```

Bindings:

```json
[]
```

4. SQL:

```sql
select * from `comments` where `comments`.`task_id` in (9999)
```

Bindings:

```json
[]
```

5. SQL:

```sql
select `tags`.*, `task_tag`.`task_id` as `pivot_task_id`, `task_tag`.`tag_id` as `pivot_tag_id`, `task_tag`.`created_at` as `pivot_created_at`, `task_tag`.`updated_at` as `pivot_updated_at` from `tags` inner join `task_tag` on `tags`.`id` = `task_tag`.`tag_id` where `task_tag`.`task_id` in (9999)
```

Bindings:

```json
[]
```

### Symfony

- Method: `GET`
- URI: `/api/tasks/9999?with=project,comments,tags`
- HTTP status: `200`
- Query count (`X-Query-Count`): **2**
- Request id (`X-Benchmark-Request-Id`): `234d6d9ec043bfe6`

1. SQL:

```sql
SELECT t0.id AS id_1, t0.title AS title_2, t0.description AS description_3, t0.status AS status_4, t0.priority AS priority_5, t0.due_date AS due_date_6, t0.created_at AS created_at_7, t0.updated_at AS updated_at_8, t0.project_id AS project_id_9 FROM tasks t0 WHERE t0.id = ?
```

Parameters:

```json
{
  "1": "9999"
}
```

2. SQL:

```sql
SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, p1_.id AS id_8, p1_.name AS name_9, p1_.description AS description_10, p1_.status AS status_11, p1_.created_at AS created_at_12, p1_.updated_at AS updated_at_13, c2_.id AS id_14, c2_.content AS content_15, c2_.created_at AS created_at_16, c2_.updated_at AS updated_at_17, t3_.id AS id_18, t3_.created_at AS created_at_19, t3_.updated_at AS updated_at_20, t4_.id AS id_21, t4_.name AS name_22, t4_.color AS color_23, t4_.created_at AS created_at_24, t4_.updated_at AS updated_at_25, t0_.project_id AS project_id_26, p1_.user_id AS user_id_27, c2_.task_id AS task_id_28, t3_.task_id AS task_id_29, t3_.tag_id AS tag_id_30 FROM tasks t0_ LEFT JOIN projects p1_ ON t0_.project_id = p1_.id LEFT JOIN comments c2_ ON t0_.id = c2_.task_id LEFT JOIN task_tag t3_ ON t0_.id = t3_.task_id LEFT JOIN tags t4_ ON t3_.tag_id = t4_.id WHERE t0_.id = ? ORDER BY t0_.id DESC
```

Parameters:

```json
{
  "1": 9999
}
```

## create_task — POST /api/tasks

### Laravel

- Method: `POST`
- URI: `/api/tasks`
- HTTP status: `201`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `9807dc2e2d5f99ad`

1. SQL:

```sql
select count(*) as aggregate from `projects` where `id` = ?
```

Bindings:

```json
[
  1
]
```

2. SQL:

```sql
insert into `tasks` (`project_id`, `title`, `description`, `status`, `priority`, `updated_at`, `created_at`) values (?, ?, ?, ?, ?, ?, ?)
```

Bindings:

```json
[
  1,
  "sql-diag-create-1787685057578",
  "Created during SQL diagnostic",
  "todo",
  "medium",
  "2026-08-25 19:10:57",
  "2026-08-25 19:10:57"
]
```

3. SQL:

```sql
select `tags`.*, `task_tag`.`task_id` as `pivot_task_id`, `task_tag`.`tag_id` as `pivot_tag_id`, `task_tag`.`created_at` as `pivot_created_at`, `task_tag`.`updated_at` as `pivot_updated_at` from `tags` inner join `task_tag` on `tags`.`id` = `task_tag`.`tag_id` where `task_tag`.`task_id` in (10640)
```

Bindings:

```json
[]
```

### Symfony

- Method: `POST`
- URI: `/api/tasks`
- HTTP status: `201`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `cbc5f77b3e42ea3d`

1. SQL:

```sql
SELECT t0.id AS id_1, t0.name AS name_2, t0.description AS description_3, t0.status AS status_4, t0.created_at AS created_at_5, t0.updated_at AS updated_at_6, t0.user_id AS user_id_7 FROM projects t0 WHERE t0.id = ?
```

Parameters:

```json
{
  "1": 1
}
```

2. SQL:

```sql
INSERT INTO tasks (title, description, status, priority, due_date, created_at, updated_at, project_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
```

Parameters:

```json
{
  "1": "sql-diag-create-1787685058254",
  "2": "Created during SQL diagnostic",
  "3": "todo",
  "4": "medium",
  "5": null,
  "6": "2026-08-25 19:10:58",
  "7": "2026-08-25 19:10:58",
  "8": 1
}
```

3. SQL:

```sql
SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, t1_.id AS id_8, t1_.created_at AS created_at_9, t1_.updated_at AS updated_at_10, t2_.id AS id_11, t2_.name AS name_12, t2_.color AS color_13, t2_.created_at AS created_at_14, t2_.updated_at AS updated_at_15, t0_.project_id AS project_id_16, t1_.task_id AS task_id_17, t1_.tag_id AS tag_id_18 FROM tasks t0_ LEFT JOIN task_tag t1_ ON t0_.id = t1_.task_id LEFT JOIN tags t2_ ON t1_.tag_id = t2_.id WHERE t0_.id = ? ORDER BY t0_.id DESC
```

Parameters:

```json
{
  "1": 10640
}
```

## update_task — PUT /api/tasks/{id}

### Laravel

- Method: `PUT`
- URI: `/api/tasks/10641`
- HTTP status: `200`
- Query count (`X-Query-Count`): **5**
- Request id (`X-Benchmark-Request-Id`): `86e5814e254bffad`

1. SQL:

```sql
select * from `tasks` where `id` = ? limit 1
```

Bindings:

```json
[
  "10641"
]
```

2. SQL:

```sql
select count(*) as aggregate from `projects` where `id` = ?
```

Bindings:

```json
[
  1
]
```

3. SQL:

```sql
update `tasks` set `title` = ?, `description` = ?, `status` = ?, `priority` = ?, `tasks`.`updated_at` = ? where `id` = ?
```

Bindings:

```json
[
  "sql-diag-updated-10641",
  "Updated during SQL diagnostic",
  "done",
  "high",
  "2026-08-25 19:10:57",
  10641
]
```

4. SQL:

```sql
select * from `tasks` where `id` = ? limit 1
```

Bindings:

```json
[
  10641
]
```

5. SQL:

```sql
select `tags`.*, `task_tag`.`task_id` as `pivot_task_id`, `task_tag`.`tag_id` as `pivot_tag_id`, `task_tag`.`created_at` as `pivot_created_at`, `task_tag`.`updated_at` as `pivot_updated_at` from `tags` inner join `task_tag` on `tags`.`id` = `task_tag`.`tag_id` where `task_tag`.`task_id` in (10641)
```

Bindings:

```json
[]
```

### Symfony

- Method: `PUT`
- URI: `/api/tasks/10641`
- HTTP status: `200`
- Query count (`X-Query-Count`): **3**
- Request id (`X-Benchmark-Request-Id`): `92312eb6ead3734a`

1. SQL:

```sql
SELECT t0.id AS id_1, t0.title AS title_2, t0.description AS description_3, t0.status AS status_4, t0.priority AS priority_5, t0.due_date AS due_date_6, t0.created_at AS created_at_7, t0.updated_at AS updated_at_8, t0.project_id AS project_id_9 FROM tasks t0 WHERE t0.id = ?
```

Parameters:

```json
{
  "1": "10641"
}
```

2. SQL:

```sql
UPDATE tasks SET title = ?, description = ?, status = ?, priority = ?, updated_at = ? WHERE id = ?
```

Parameters:

```json
{
  "1": "sql-diag-updated-10641",
  "2": "Updated during SQL diagnostic",
  "3": "done",
  "4": "high",
  "5": "2026-08-25 19:10:58",
  "6": 10641
}
```

3. SQL:

```sql
SELECT t0_.id AS id_0, t0_.title AS title_1, t0_.description AS description_2, t0_.status AS status_3, t0_.priority AS priority_4, t0_.due_date AS due_date_5, t0_.created_at AS created_at_6, t0_.updated_at AS updated_at_7, t1_.id AS id_8, t1_.created_at AS created_at_9, t1_.updated_at AS updated_at_10, t2_.id AS id_11, t2_.name AS name_12, t2_.color AS color_13, t2_.created_at AS created_at_14, t2_.updated_at AS updated_at_15, t0_.project_id AS project_id_16, t1_.task_id AS task_id_17, t1_.tag_id AS tag_id_18 FROM tasks t0_ LEFT JOIN task_tag t1_ ON t0_.id = t1_.task_id LEFT JOIN tags t2_ ON t1_.tag_id = t2_.id WHERE t0_.id = ? ORDER BY t0_.id DESC
```

Parameters:

```json
{
  "1": 10641
}
```

## delete_task — DELETE /api/tasks/{id}

### Laravel

- Method: `DELETE`
- URI: `/api/tasks/10642`
- HTTP status: `204`
- Query count (`X-Query-Count`): **2**
- Request id (`X-Benchmark-Request-Id`): `23aacf1e8ff4dad0`

1. SQL:

```sql
select * from `tasks` where `id` = ? limit 1
```

Bindings:

```json
[
  "10642"
]
```

2. SQL:

```sql
delete from `tasks` where `id` = ?
```

Bindings:

```json
[
  10642
]
```

### Symfony

- Method: `DELETE`
- URI: `/api/tasks/10642`
- HTTP status: `204`
- Query count (`X-Query-Count`): **4**
- Request id (`X-Benchmark-Request-Id`): `9a4a10279c9f5827`

1. SQL:

```sql
SELECT t0.id AS id_1, t0.title AS title_2, t0.description AS description_3, t0.status AS status_4, t0.priority AS priority_5, t0.due_date AS due_date_6, t0.created_at AS created_at_7, t0.updated_at AS updated_at_8, t0.project_id AS project_id_9 FROM tasks t0 WHERE t0.id = ?
```

Parameters:

```json
{
  "1": "10642"
}
```

2. SQL:

```sql
SELECT t0.id AS id_1, t0.content AS content_2, t0.created_at AS created_at_3, t0.updated_at AS updated_at_4, t0.task_id AS task_id_5 FROM comments t0 WHERE t0.task_id = ?
```

Parameters:

```json
{
  "1": 10642
}
```

3. SQL:

```sql
SELECT t0.id AS id_1, t0.created_at AS created_at_2, t0.updated_at AS updated_at_3, t0.task_id AS task_id_4, t0.tag_id AS tag_id_5 FROM task_tag t0 WHERE t0.task_id = ?
```

Parameters:

```json
{
  "1": 10642
}
```

4. SQL:

```sql
DELETE FROM tasks WHERE id = ?
```

Parameters:

```json
{
  "1": 10642
}
```

## Summary

| Scenariusz | Laravel SQL | Symfony SQL |
| --- | ---: | ---: |
| list_per_page_15 — GET /api/tasks?per_page=15 | 2 | 3 |
| list_per_page_100 — GET /api/tasks?per_page=100 | 2 | 3 |
| single_task — GET /api/tasks/{id} | 1 | 1 |
| list_with_project — GET …&with=project | 3 | 3 |
| list_with_comments — GET …&with=comments | 3 | 3 |
| list_with_tags — GET …&with=tags | 3 | 3 |
| list_with_all — GET …&with=project,comments,tags | 5 | 3 |
| list_100_with_all — GET …?per_page=100&with=project,comments,tags | 5 | 3 |
| single_with_all — GET /api/tasks/{id}?with=project,comments,tags | 5 | 2 |
| create_task — POST /api/tasks | 3 | 3 |
| update_task — PUT /api/tasks/{id} | 5 | 3 |
| delete_task — DELETE /api/tasks/{id} | 2 | 4 |
