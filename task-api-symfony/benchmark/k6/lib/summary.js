import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.1/index.js';

import { getResultsDir } from './config.js';

function isoTimestamp() {
  return new Date().toISOString().replace(/[:.]/g, '-');
}

function metricValue(m) {
  if (!m || m.values === undefined) return null;
  return m.values;
}

function pickNumber(values, keys) {
  if (!values) return null;
  for (const k of keys) {
    if (values[k] !== undefined && typeof values[k] === 'number') {
      return values[k];
    }
  }
  return null;
}

function p95Ms(values) {
  if (!values) return null;
  const k = 'p(95)';
  if (values[k] !== undefined && typeof values[k] === 'number') {
    return values[k];
  }
  return null;
}

function effectiveVus(data) {
  if (__ENV.VUS) {
    return __ENV.VUS;
  }
  const m = data.metrics && data.metrics.vus_max;
  if (m && m.values && m.values.max !== undefined) {
    return String(m.values.max);
  }
  return '';
}

function statusDistribution(data) {
  const out = {};
  for (const name of Object.keys(data.metrics || {})) {
    const prefix = 'http_status_';
    if (name.startsWith(prefix) && name !== 'http_status_other') {
      const code = name.slice(prefix.length);
      const v = data.metrics[name];
      const c = v && v.values ? v.values.count : 0;
      if (c > 0) {
        out[code] = c;
      }
    }
  }
  const other = data.metrics.http_status_other;
  if (other && other.values && other.values.count > 0) {
    out.other = other.values.count;
  }
  return out;
}

function buildCsvRow(scenario, data) {
  const httpDur = metricValue(data.metrics.http_req_duration);
  const httpReqs = metricValue(data.metrics.http_reqs);
  const failed = metricValue(data.metrics.http_req_failed);
  const recv = metricValue(data.metrics.data_received);
  const sent = metricValue(data.metrics.data_sent);
  const xqc = metricValue(data.metrics.x_query_count);
  const xrt = metricValue(data.metrics.x_response_time_ms);

  const fields = [
    scenario,
    effectiveVus(data),
    __ENV.DURATION || '',
    data.state.testRunDurationMs ? String(Math.round(data.state.testRunDurationMs)) : '',
    pickNumber(httpReqs, ['count']),
    pickNumber(httpReqs, ['rate']),
    pickNumber(httpDur, ['avg']),
    pickNumber(httpDur, ['med', 'median']),
    p95Ms(httpDur),
    pickNumber(httpDur, ['max']),
    pickNumber(failed, ['rate']),
    pickNumber(recv, ['rate']),
    pickNumber(sent, ['rate']),
    pickNumber(xqc, ['avg', 'med']),
    pickNumber(xrt, ['avg', 'med']),
    JSON.stringify(statusDistribution(data)),
  ];

  return fields.map((v) => (v === null || v === undefined ? '' : String(v))).join(',');
}

const csvHeader =
  'scenario,vus_env,duration_env,wall_clock_ms,http_reqs_total,http_reqs_per_s,http_req_duration_avg_ms,http_req_duration_med_ms,http_req_duration_p95_ms,http_req_duration_max_ms,http_req_failed_rate,data_received_bytes_per_s,data_sent_bytes_per_s,x_query_count_avg,x_response_time_ms_avg,http_status_distribution_json\n';

/**
 * @param {string} scenarioSlug e.g. "01-crud"
 */
export function makeHandleSummary(scenarioSlug) {
  return function handleSummary(data) {
    const dir = getResultsDir();
    const stamp = isoTimestamp();
    const base = `${dir}/${scenarioSlug}-${stamp}`;
    const summaryPayload = {
      scenario: scenarioSlug,
      generatedAt: new Date().toISOString(),
      baseUrl: __ENV.BASE_URL || null,
      vus: effectiveVus(data) || null,
      duration: __ENV.DURATION || null,
      metrics: data.metrics,
      state: data.state,
      httpStatusDistribution: statusDistribution(data),
      notes:
        'k6 http_req_duration is client-side (network + PHP + DB). X-Response-Time-Ms / X-Query-Count are server-reported when X-Benchmark-Metrics:1.',
    };

    const files = {};
    files[`${base}.json`] = JSON.stringify(summaryPayload, null, 2);
    files[`${base}.csv`] = csvHeader + buildCsvRow(scenarioSlug, data);
    files.stdout = textSummary(data, { indent: ' ', enableColors: false });

    return files;
  };
}
