#!/usr/bin/env python3

from __future__ import annotations

import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

SCRIPT_DIR = Path(__file__).resolve().parent

LARAVEL_BASE = os.environ.get("LARAVEL_BASE", "http://localhost:8080").rstrip("/")
SYMFONY_BASE = os.environ.get("SYMFONY_BASE", "http://localhost:8081").rstrip("/")
PROJECT_ID = int(os.environ.get("PROJECT_ID", "1"))
SINGLE_TASK_ID = os.environ.get("SINGLE_TASK_ID", "10000")
CURL_TIMEOUT_SEC = float(os.environ.get("CURL_TIMEOUT_SEC", "30"))

LARAVEL_LOG = SCRIPT_DIR / "task-api-laravel" / "storage" / "logs" / "sql_benchmark.log"
SYMFONY_LOG = SCRIPT_DIR / "task-api-symfony" / "var" / "log" / "sql_benchmark.log"
REPORT_PATH = SCRIPT_DIR / "benchmark_sql_analysis.md"

HEADER_ACCEPT = "application/json"
HEADER_CONTENT = "application/json"


class ScenarioError(Exception):
    """Błąd przerywający dany scenariusz / cały przebieg."""


@dataclass
class HttpResult:
    status: int
    body: bytes
    headers: dict[str, str]
    error: str | None = None


@dataclass
class MeasuredRequest:
    framework: str
    scenario: str
    method: str
    uri: str
    status: int
    query_count: int | None
    request_id: str
    queries: list[dict[str, Any]] = field(default_factory=list)
    response_body: bytes = b""



SCENARIOS: list[tuple[str, str]] = [
    ("list_per_page_15", "list_per_page_15 — GET /api/tasks?per_page=15"),
    ("list_per_page_100", "list_per_page_100 — GET /api/tasks?per_page=100"),
    ("single_task", "single_task — GET /api/tasks/{id}"),
    ("list_with_project", "list_with_project — GET …&with=project"),
    ("list_with_comments", "list_with_comments — GET …&with=comments"),
    ("list_with_tags", "list_with_tags — GET …&with=tags"),
    ("list_with_all", "list_with_all — GET …&with=project,comments,tags"),
    ("list_100_with_all", "list_100_with_all — GET …?per_page=100&with=project,comments,tags"),
    ("single_with_all", "single_with_all — GET /api/tasks/{id}?with=project,comments,tags"),
    ("create_task", "create_task — POST /api/tasks"),
    ("update_task", "update_task — PUT /api/tasks/{id}"),
    ("delete_task", "delete_task — DELETE /api/tasks/{id}"),
]


def die(msg: str, code: int = 1) -> None:
    print(f"ERROR: {msg}", file=sys.stderr)
    raise SystemExit(code)


def http_request(
    method: str,
    url: str,
    *,
    body: dict[str, Any] | None = None,
    benchmark: bool = False,
) -> HttpResult:
    data = None
    headers = {
        "Accept": HEADER_ACCEPT,
    }
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = HEADER_CONTENT
    if benchmark:
        headers["X-Benchmark-Metrics"] = "1"
        headers["X-Benchmark-Sql-Log"] = "1"

    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=CURL_TIMEOUT_SEC) as resp:
            raw = resp.read()
            hdrs = {k.lower(): v for k, v in resp.headers.items()}
            return HttpResult(status=resp.status, body=raw, headers=hdrs)
    except urllib.error.HTTPError as e:
        raw = e.read() if e.fp else b""
        hdrs = {k.lower(): v for k, v in (e.headers.items() if e.headers else [])}
        return HttpResult(status=e.code, body=raw, headers=hdrs, error=str(e))
    except urllib.error.URLError as e:
        raise ScenarioError(f"Aplikacja nie odpowiada ({url}): {e.reason}") from e
    except TimeoutError as e:
        raise ScenarioError(f"Timeout połączenia ({url})") from e


def parse_json_body(raw: bytes) -> Any:
    if not raw:
        return None
    try:
        return json.loads(raw.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError):
        return None


def extract_task_id(payload: Any) -> int | None:
    if not isinstance(payload, dict):
        return None
    node = payload.get("data", payload)
    if isinstance(node, dict) and "id" in node:
        try:
            return int(node["id"])
        except (TypeError, ValueError):
            return None
    return None


def expect_status(result: HttpResult, expected: set[int], context: str) -> None:
    if result.status not in expected:
        snippet = (result.body or b"")[:400].decode("utf-8", errors="replace")
        raise ScenarioError(
            f"{context}: nieoczekiwany status HTTP {result.status} "
            f"(oczekiwano {sorted(expected)}). Body: {snippet}"
        )


def create_helper_task(base: str, framework: str, label: str) -> int:
    result = http_request(
        "POST",
        f"{base}/api/tasks",
        body={
            "project_id": PROJECT_ID,
            "title": f"sql-diag-{label}-{int(time.time() * 1000)}",
            "description": "Temporary task for SQL diagnostic (helper)",
            "status": "todo",
            "priority": "medium",
        },
        benchmark=False,
    )
    expect_status(result, {201}, f"{framework}: POST przygotowujący rekord")
    task_id = extract_task_id(parse_json_body(result.body))
    if task_id is None:
        raise ScenarioError(f"{framework}: nie udało się odczytać ID z odpowiedzi POST")
    return task_id


def silent_delete(base: str, task_id: int) -> None:
    try:
        http_request("DELETE", f"{base}/api/tasks/{task_id}", benchmark=False)
    except ScenarioError:
        pass


def ensure_single_task_id(base: str, framework: str) -> int:
    preferred = int(SINGLE_TASK_ID)
    probe = http_request("GET", f"{base}/api/tasks/{preferred}", benchmark=False)
    if probe.status == 200:
        return preferred

    listing = http_request("GET", f"{base}/api/tasks?per_page=1", benchmark=False)
    expect_status(listing, {200}, f"{framework}: GET lista (wyszukiwanie ID do GET single)")
    payload = parse_json_body(listing.body)
    if isinstance(payload, dict):
        data = payload.get("data")
        if isinstance(data, list) and data:
            first = data[0]
            if isinstance(first, dict) and "id" in first:
                return int(first["id"])
    raise ScenarioError(
        f"{framework}: brak istniejącego taska do GET single "
        f"(ID={preferred} nie istnieje, lista pusta)"
    )


def read_log_entries(path: Path, framework: str) -> list[dict[str, Any]]:
    if not path.is_file():
        return []
    entries: list[dict[str, Any]] = []
    text = path.read_text(encoding="utf-8", errors="replace")
    for line in text.splitlines():
        line = line.strip()
        if not line:
            continue
        if framework == "laravel":
            m = re.search(r"sql_dump\s+(\{.*\})\s*$", line)
            if not m:
                continue
            try:
                entries.append(json.loads(m.group(1)))
            except json.JSONDecodeError:
                continue
        else:
            try:
                entries.append(json.loads(line))
            except json.JSONDecodeError:
                continue
    return entries


def find_log_by_request_id(
    path: Path,
    framework: str,
    request_id: str,
    *,
    retries: int = 10,
    delay_sec: float = 0.05,
) -> dict[str, Any]:
    for _ in range(retries):
        for entry in reversed(read_log_entries(path, framework)):
            if str(entry.get("request_id", "")) == request_id:
                return entry
        time.sleep(delay_sec)
    raise ScenarioError(
        f"{framework}: nie znaleziono request_id={request_id} w logu {path}"
    )


def normalize_queries(entry: dict[str, Any]) -> list[dict[str, Any]]:
    raw = entry.get("queries") or []
    out: list[dict[str, Any]] = []
    if not isinstance(raw, list):
        return out
    for q in raw:
        if not isinstance(q, dict):
            continue
        sql = q.get("sql") or ""
        params = q.get("params")
        if params is None:
            params = q.get("bindings")
        out.append({"sql": sql, "params": params})
    return out


def measure(
    framework: str,
    base: str,
    log_path: Path,
    scenario_key: str,
    method: str,
    path_and_query: str,
    *,
    body: dict[str, Any] | None = None,
    expected_status: set[int],
) -> MeasuredRequest:
    url = f"{base}{path_and_query}"
    result = http_request(method, url, body=body, benchmark=True)
    expect_status(result, expected_status, f"{framework}/{scenario_key} {method} {path_and_query}")

    request_id = result.headers.get("x-benchmark-request-id", "").strip()
    if not request_id:
        raise ScenarioError(
            f"{framework}/{scenario_key}: brak nagłówka X-Benchmark-Request-Id "
            "(czy mechanizm X-Benchmark-Sql-Log jest aktywny?)"
        )

    qc_raw = result.headers.get("x-query-count")
    query_count = int(qc_raw) if qc_raw is not None and str(qc_raw).isdigit() else None

    entry = find_log_by_request_id(log_path, framework, request_id)
    queries = normalize_queries(entry)

    if query_count is None:
        query_count = int(entry.get("query_count") or len(queries))

    return MeasuredRequest(
        framework=framework,
        scenario=scenario_key,
        method=method,
        uri=path_and_query,
        status=result.status,
        query_count=query_count,
        request_id=request_id,
        queries=queries,
        response_body=result.body,
    )


def run_framework(framework: str, base: str, log_path: Path) -> dict[str, MeasuredRequest]:
    print(f"\n=== {framework.upper()} ({base}) ===")
    results: dict[str, MeasuredRequest] = {}
    single_id = ensure_single_task_id(base, framework)

    read_gets: list[tuple[str, str]] = [
        ("list_per_page_15", "/api/tasks?per_page=15"),
        ("list_per_page_100", "/api/tasks?per_page=100"),
        ("single_task", f"/api/tasks/{single_id}"),
        ("list_with_project", "/api/tasks?per_page=15&with=project"),
        ("list_with_comments", "/api/tasks?per_page=15&with=comments"),
        ("list_with_tags", "/api/tasks?per_page=15&with=tags"),
        ("list_with_all", "/api/tasks?per_page=15&with=project,comments,tags"),
        ("list_100_with_all", "/api/tasks?per_page=100&with=project,comments,tags"),
        ("single_with_all", f"/api/tasks/{single_id}?with=project,comments,tags"),
    ]

    for key, path in read_gets:
        print(f"  {key} …")
        results[key] = measure(
            framework, base, log_path, key, "GET", path, expected_status={200},
        )

    # create_task — mierzony POST; cleanup poza dumpem SQL
    print("  create_task …")
    created = measure(
        framework,
        base,
        log_path,
        "create_task",
        "POST",
        "/api/tasks",
        body={
            "project_id": PROJECT_ID,
            "title": f"sql-diag-create-{int(time.time() * 1000)}",
            "description": "Created during SQL diagnostic",
            "status": "todo",
            "priority": "medium",
        },
        expected_status={201},
    )
    results["create_task"] = created
    created_id = extract_task_id(parse_json_body(created.response_body))
    if created_id is not None:
        silent_delete(base, created_id)

    # update_task — helper POST → mierzony PUT → helper DELETE
    print("  update_task …")
    update_id = create_helper_task(base, framework, "update")
    try:
        results["update_task"] = measure(
            framework,
            base,
            log_path,
            "update_task",
            "PUT",
            f"/api/tasks/{update_id}",
            body={
                "project_id": PROJECT_ID,
                "title": f"sql-diag-updated-{update_id}",
                "description": "Updated during SQL diagnostic",
                "status": "done",
                "priority": "high",
            },
            expected_status={200},
        )
    finally:
        silent_delete(base, update_id)

    # delete_task — helper POST → mierzony DELETE
    print("  delete_task …")
    delete_id = create_helper_task(base, framework, "delete")
    results["delete_task"] = measure(
        framework,
        base,
        log_path,
        "delete_task",
        "DELETE",
        f"/api/tasks/{delete_id}",
        expected_status={204},
    )

    for key, _ in SCENARIOS:
        m = results[key]
        print(f"    {key}: HTTP {m.status}, queries={m.query_count}, request_id={m.request_id}")

    return results


def format_params(params: Any) -> str:
    if params is None:
        return "(brak)"
    try:
        return json.dumps(params, ensure_ascii=False, indent=2, default=str)
    except TypeError:
        return repr(params)


def render_framework_block(m: MeasuredRequest, bindings_label: str) -> list[str]:
    lines = [
        f"### {m.framework.capitalize()}",
        "",
        f"- Method: `{m.method}`",
        f"- URI: `{m.uri}`",
        f"- HTTP status: `{m.status}`",
        f"- Query count (`X-Query-Count`): **{m.query_count}**",
        f"- Request id (`X-Benchmark-Request-Id`): `{m.request_id}`",
        "",
    ]
    if not m.queries:
        lines.append("_Brak zapytań w dumpie SQL._")
        lines.append("")
        return lines

    for i, q in enumerate(m.queries, start=1):
        lines.append(f"{i}. SQL:")
        lines.append("")
        lines.append("```sql")
        lines.append(str(q.get("sql") or "").strip() or "(empty)")
        lines.append("```")
        lines.append("")
        lines.append(f"{bindings_label}:")
        lines.append("")
        lines.append("```json")
        lines.append(format_params(q.get("params")))
        lines.append("```")
        lines.append("")
    return lines


def write_report(
    laravel: dict[str, MeasuredRequest],
    symfony: dict[str, MeasuredRequest],
) -> None:
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")
    lines: list[str] = [
        "# SQL comparison",
        "",
        f"Generated: `{now}`",
        "",
        "Źródło: rzeczywiste zapytania z `sql_benchmark.log` "
        "(nagłówki `X-Benchmark-Metrics: 1`, `X-Benchmark-Sql-Log: 1`).",
        "",
        "Scenariusze odpowiadają `benchmark_read_operations.sh` oraz "
        "`benchmark_write_operations.sh` (po jednym requestcie na scenariusz).",
        "",
        f"- Laravel: `{LARAVEL_BASE}` → `{LARAVEL_LOG.relative_to(SCRIPT_DIR)}`",
        f"- Symfony: `{SYMFONY_BASE}` → `{SYMFONY_LOG.relative_to(SCRIPT_DIR)}`",
        "",
        "Operacje pomocnicze POST/DELETE (przygotowanie / sprzątanie) "
        "nie są uwzględnione w wynikach scenariuszy.",
        "",
    ]

    for key, title in SCENARIOS:
        lines.append(f"## {title}")
        lines.append("")
        lines.extend(render_framework_block(laravel[key], "Bindings"))
        lines.extend(render_framework_block(symfony[key], "Parameters"))

    lines.append("## Summary")
    lines.append("")
    lines.append("| Scenariusz | Laravel SQL | Symfony SQL |")
    lines.append("| --- | ---: | ---: |")
    for key, title in SCENARIOS:
        lq = laravel[key].query_count
        sq = symfony[key].query_count
        lines.append(f"| {title} | {lq} | {sq} |")
    lines.append("")

    REPORT_PATH.write_text("\n".join(lines), encoding="utf-8")


def check_reachable(base: str, name: str) -> None:
    try:
        http_request("GET", f"{base}/api/tasks?per_page=1", benchmark=False)
    except ScenarioError as e:
        die(f"{name}: {e}")


def main() -> int:
    print("SQL diagnostic: Laravel vs Symfony")
    print(f"  Laravel: {LARAVEL_BASE}")
    print(f"  Symfony: {SYMFONY_BASE}")
    print(f"  Report:  {REPORT_PATH}")

    check_reachable(LARAVEL_BASE, "Laravel")
    check_reachable(SYMFONY_BASE, "Symfony")

    # Upewnij się, że katalogi logów istnieją (Symfony tworzy plik przy pierwszym dumpie)
    LARAVEL_LOG.parent.mkdir(parents=True, exist_ok=True)
    SYMFONY_LOG.parent.mkdir(parents=True, exist_ok=True)

    try:
        laravel = run_framework("laravel", LARAVEL_BASE, LARAVEL_LOG)
        symfony = run_framework("symfony", SYMFONY_BASE, SYMFONY_LOG)
    except ScenarioError as e:
        die(str(e))

    write_report(laravel, symfony)
    print(f"\nRaport zapisany: {REPORT_PATH}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
