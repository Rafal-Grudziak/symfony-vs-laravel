
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';


function textSummary(data, opts) {
  opts = opts || {};
  const indent = opts.indent || ' ';
  const lines = [];
  lines.push('     checks.........................: ' + summarizeChecks(data));
  const names = Object.keys(data.metrics || {}).sort();
  for (let i = 0; i < names.length; i++) {
    const name = names[i];
    const m = data.metrics[name];
    const v = m && m.values ? m.values : {};
    const parts = [];
    const keys = Object.keys(v);
    for (let j = 0; j < keys.length; j++) {
      const k = keys[j];
      let val = v[k];
      if (typeof val === 'number') {
        val = Math.round(val * 1000) / 1000;
      }
      parts.push(k + '=' + val);
    }
    lines.push(indent + name + ': ' + parts.join(' '));
  }
  return lines.join('\n') + '\n';
}

function summarizeChecks(data) {
  if (!data.root_group || !data.root_group.checks) return 'n/a';
  let passes = 0;
  let fails = 0;
  for (let i = 0; i < data.root_group.checks.length; i++) {
    passes += data.root_group.checks[i].passes || 0;
    fails += data.root_group.checks[i].fails || 0;
  }
  const total = passes + fails;
  const pct = total ? ((passes / total) * 100).toFixed(2) : '0.00';
  return pct + '% — ' + passes + ' / ' + total;
}

const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const FRAMEWORK = __ENV.FRAMEWORK || 'laravel';
const VUS = Math.max(1, parseInt(__ENV.VUS || '10', 10) || 10);
const LOAD_LEVEL = __ENV.LOAD_LEVEL || String(VUS);
/** Fixed load duration for constant-vus (no ramp-up / ramp-down in measured window). */
const DURATION = __ENV.DURATION || __ENV.STEADY_DURATION || '30s';
/** Think-time between requests; set to 0 for maximum load. */
const SLEEP_DURATION = parseFloat(__ENV.SLEEP_DURATION || '0.2');
const RESULTS_DIR = (__ENV.RESULTS_DIR || '.').replace(/\/$/, '');
const RESULT_PREFIX = __ENV.RESULT_PREFIX || `${FRAMEWORK}_${LOAD_LEVEL}`;


const GRACEFUL_STOP = __ENV.GRACEFUL_STOP || '60s';

const ENDPOINT_TAG = 'tasks_with_relations';

function buildUrl() {
  const qs =
    'per_page=' +
    encodeURIComponent('100') +
    '&with=' +
    encodeURIComponent('project,comments,tags');
  return `${BASE_URL}/api/tasks?${qs}`;
}

const URL = buildUrl();

const requestTags = {
  framework: FRAMEWORK,
  load: String(LOAD_LEVEL),
  endpoint: ENDPOINT_TAG,
};


const successfulResponses = new Counter('successful_responses');

const validationFailures = new Counter('validation_failures');

const validationErrors = new Rate('validation_errors');
const endpointDuration = new Trend('endpoint_duration', true);


export const options = {
  scenarios: {
    steady_load: {
      executor: 'constant-vus',
      vus: VUS,
      duration: DURATION,
      gracefulStop: GRACEFUL_STOP,
    },
  },
  thresholds: {},
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)'],
};

export function setup() {
  return {
    url: URL,
    framework: FRAMEWORK,
    load: String(LOAD_LEVEL),
    vus: VUS,
    duration: DURATION,
    executor: 'constant-vus',
  };
}

export default function () {
  const res = http.get(URL, {
    headers: {
      Accept: 'application/json',
    },
    tags: requestTags,
    timeout: '60s',
  });

  endpointDuration.add(res.timings.duration, requestTags);

  let jsonOk = false;
  let hasData = false;
  try {
    const body = res.json();
    jsonOk = body !== null && body !== undefined;
    if (jsonOk) {
      if (Array.isArray(body)) {
        hasData = body.length > 0;
      } else if (typeof body === 'object') {
        if (Array.isArray(body.data)) {
          hasData = body.data.length > 0;
        } else if (body.data && typeof body.data === 'object') {
          hasData = true;
        } else {
          hasData = Object.keys(body).length > 0;
        }
      }
    }
  } catch (e) {
    jsonOk = false;
  }

  const statusOk = res.status === 200;
  check(res, {
    'status is 200': (r) => r.status === 200,
    'body is valid JSON': () => jsonOk,
    'response contains task data': () => hasData,
  });

  // Separate HTTP failures (k6 http_req_failed) from application/body validation.
  if (statusOk && jsonOk && hasData) {
    successfulResponses.add(1, requestTags);
    validationErrors.add(0, requestTags);
  } else if (statusOk && (!jsonOk || !hasData)) {
    validationFailures.add(1, requestTags);
    validationErrors.add(1, requestTags);
  } else {
    // Non-200 / network error: counted by http_req_failed only.
    validationErrors.add(0, requestTags);
  }

  if (SLEEP_DURATION > 0) {
    sleep(SLEEP_DURATION);
  }
}

function metricValues(data, name) {
  const m = data.metrics && data.metrics[name];
  return m && m.values ? m.values : null;
}

function pick(values, key) {
  if (!values || values[key] === undefined) return null;
  return values[key];
}

function buildReadableReport(data) {
  const httpDur = metricValues(data, 'http_req_duration');
  const httpReqs = metricValues(data, 'http_reqs');
  const httpFail = metricValues(data, 'http_req_failed');
  const waiting = metricValues(data, 'http_req_waiting');
  const recv = metricValues(data, 'data_received');
  const sent = metricValues(data, 'data_sent');
  const iters = metricValues(data, 'iterations');
  const iterDur = metricValues(data, 'iteration_duration');
  const vus = metricValues(data, 'vus');
  const vusMax = metricValues(data, 'vus_max');
  const dropped = metricValues(data, 'dropped_iterations');
  const okC = metricValues(data, 'successful_responses');
  const valFail = metricValues(data, 'validation_failures');
  const valRate = metricValues(data, 'validation_errors');
  const endp = metricValues(data, 'endpoint_duration');

  const reqCount = pick(httpReqs, 'count');
  const httpFailRate = pick(httpFail, 'rate');
  const httpFailedCount =
    reqCount !== null && httpFailRate !== null ? Math.round(httpFailRate * reqCount) : null;

  const lines = [];
  lines.push('Load benchmark — scenario report');
  lines.push('================================');
  lines.push(`Framework:     ${FRAMEWORK}`);
  lines.push(`Load level:    ${LOAD_LEVEL} VU`);
  lines.push(`Base URL:      ${BASE_URL}`);
  lines.push(`Endpoint:      GET /api/tasks?per_page=100&with=project,comments,tags`);
  lines.push(`Executor:      constant-vus`);
  lines.push(`Duration:      ${DURATION} (metrics cover this window only; warm-up is outside k6)`);
  lines.push(`Sleep:         ${SLEEP_DURATION}s`);
  lines.push(
    `Test duration: ${
      data.state && data.state.testRunDurationMs
        ? Math.round(data.state.testRunDurationMs) + ' ms'
        : 'n/a'
    }`,
  );
  lines.push('');
  lines.push('HTTP request duration (ms)');
  lines.push(`  avg:    ${pick(httpDur, 'avg')}`);
  lines.push(`  med:    ${pick(httpDur, 'med')}`);
  lines.push(`  p(90):  ${pick(httpDur, 'p(90)')}`);
  lines.push(`  p(95):  ${pick(httpDur, 'p(95)')}`);
  lines.push(`  min:    ${pick(httpDur, 'min')}`);
  lines.push(`  max:    ${pick(httpDur, 'max')}`);
  lines.push('');
  lines.push('Throughput');
  lines.push(`  http_reqs count: ${pick(httpReqs, 'count')}`);
  lines.push(`  http_reqs rate:  ${pick(httpReqs, 'rate')} /s`);
  lines.push(`  iterations:      ${pick(iters, 'count')}`);
  lines.push(`  iter rate:       ${pick(iters, 'rate')} /s`);
  lines.push(
    `  dropped iters:   ${pick(dropped, 'count') !== null ? pick(dropped, 'count') : 0}`,
  );
  lines.push('');
  lines.push('HTTP failures (http_req_failed — non-2xx / transport)');
  lines.push(`  rate:             ${httpFailRate}`);
  lines.push(`  estimated count:  ${httpFailedCount}`);
  lines.push('');
  lines.push('Validation (check / body — separate from HTTP failures)');
  lines.push(`  successful_responses: ${pick(okC, 'count')}`);
  lines.push(`  validation_failures:  ${pick(valFail, 'count') !== null ? pick(valFail, 'count') : 0}`);
  lines.push(`  validation_errors:    ${pick(valRate, 'rate')}`);
  lines.push('');
  lines.push('Timing / data');
  lines.push(`  http_req_waiting avg (ms): ${pick(waiting, 'avg')}`);
  lines.push(`  endpoint_duration avg:     ${pick(endp, 'avg')}`);
  lines.push(`  iteration_duration avg:    ${pick(iterDur, 'avg')}`);
  lines.push(`  data_received (bytes):     ${pick(recv, 'count')}`);
  lines.push(`  data_sent (bytes):         ${pick(sent, 'count')}`);
  lines.push(`  vus max:                   ${pick(vusMax, 'max')}`);
  lines.push(`  vus value:                 ${pick(vus, 'value')}`);
  lines.push('');
  if (data.root_group && data.root_group.checks) {
    lines.push('Checks');
    for (const c of data.root_group.checks) {
      lines.push(`  ${c.name}: passes=${c.passes} fails=${c.fails}`);
    }
    lines.push('');
  }
  if (data.thresholds) {
    lines.push('Thresholds');
    for (const [name, t] of Object.entries(data.thresholds)) {
      const ok = t && t.ok === true ? 'PASS' : 'FAIL';
      lines.push(`  [${ok}] ${name}`);
    }
    lines.push('');
  }
  return lines.join('\n');
}

export function handleSummary(data) {
  const summaryPath = `${RESULTS_DIR}/${RESULT_PREFIX}_summary.json`;
  const reportPath = `${RESULTS_DIR}/${RESULT_PREFIX}_report.txt`;
  const shortCsvPath = `${RESULTS_DIR}/${RESULT_PREFIX}_metrics.csv`;

  const httpDur = metricValues(data, 'http_req_duration');
  const httpReqs = metricValues(data, 'http_reqs');
  const httpFail = metricValues(data, 'http_req_failed');
  const recv = metricValues(data, 'data_received');
  const sent = metricValues(data, 'data_sent');
  const iters = metricValues(data, 'iterations');
  const okC = metricValues(data, 'successful_responses');
  const valFail = metricValues(data, 'validation_failures');

  const reqCount = pick(httpReqs, 'count');
  const httpFailRate = pick(httpFail, 'rate');
  const httpFailedCount =
    reqCount !== null && httpFailRate !== null ? Math.round(httpFailRate * reqCount) : '';

  const csvHeader =
    'framework,vus,requests,requests_per_second,avg_ms,median_ms,p90_ms,p95_ms,min_ms,max_ms,http_error_rate,http_failed_requests,successful_responses,validation_failures,iterations,data_received,data_sent\n';
  const csvRow = [
    FRAMEWORK,
    LOAD_LEVEL,
    pick(httpReqs, 'count'),
    pick(httpReqs, 'rate'),
    pick(httpDur, 'avg'),
    pick(httpDur, 'med'),
    pick(httpDur, 'p(90)'),
    pick(httpDur, 'p(95)'),
    pick(httpDur, 'min'),
    pick(httpDur, 'max'),
    httpFailRate,
    httpFailedCount,
    pick(okC, 'count'),
    pick(valFail, 'count') !== null ? pick(valFail, 'count') : 0,
    pick(iters, 'count'),
    pick(recv, 'count'),
    pick(sent, 'count'),
  ]
    .map((v) => (v === null || v === undefined ? '' : String(v)))
    .join(',');

  const out = {};
  out[summaryPath] = JSON.stringify(data, null, 2);
  out[reportPath] = buildReadableReport(data);
  out[shortCsvPath] = csvHeader + csvRow + '\n';
  out.stdout = textSummary(data, { indent: ' ', enableColors: true });
  return out;
}
