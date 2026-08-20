/* Analytics charts: role-aware loaders for student/admin/company dashboards. */
const inPhpFolder = window.location.pathname.includes('/php/');
const analyticsPath = inPhpFolder ? 'analytics.php' : 'php/analytics.php';

const STATUS_COLORS = ['#22C55E', '#3B82F6', '#F59E0B', '#10B981', '#8B5CF6', '#EF4444', '#6B7280'];
const APP_STATUS_COLORS = ['#F59E0B', '#3B82F6', '#22C55E', '#EF4444'];

const CHART_TEXT  = '#A1A1AA';
const CHART_GRID  = 'rgba(255,255,255,0.06)';
const CHART_FONT  = { family: "'Inter', system-ui, sans-serif", size: 11, weight: '500' };
const CHART_FONT_SM = { family: "'Inter', system-ui, sans-serif", size: 10.5, weight: '500' };

async function fetchAnalytics(scope) {
  try {
    const r = await fetch(analyticsPath + '?scope=' + encodeURIComponent(scope));
    const d = await r.json();
    return d.success ? d : null;
  } catch (e) { return null; }
}

function showAnalyticsSkeleton(el) {
  el.innerHTML =
    '<div class="analytics-skeleton">' +
      '<div class="sk-bar"></div>' +
      '<div class="sk-row"><div class="sk-chart"></div><div class="sk-chart"></div><div class="sk-chart"></div></div>' +
    '</div>';
}

function renderAnalyticsError(el, msg) {
  el.innerHTML =
    '<div class="analytics-error">' +
      '<div class="err-icon">&#9888;</div>' +
      '<p>' + (msg || 'Analytics could not be loaded.') + '</p>' +
      '<button type="button" class="btn btn-secondary" onclick="window.retryAnalytics()">Retry</button>' +
    '</div>';
}

/* ---- chart config helpers ---- */

function chartDefaults() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    devicePixelRatio: Math.max(window.devicePixelRatio || 1, 1.5),
    plugins: {
      legend: {
        display: false,
        position: 'bottom',
        labels: { color: CHART_TEXT, font: CHART_FONT, boxWidth: 10, boxHeight: 10, padding: 12 }
      },
      tooltip: {
        backgroundColor: '#1A1A1A',
        titleColor: '#E4E4E7',
        bodyColor: '#A1A1AA',
        borderColor: '#2A2A2A',
        borderWidth: 1,
        padding: 10,
        cornerRadius: 8,
        titleFont: { family: "'Inter', system-ui, sans-serif", size: 12, weight: '600' },
        bodyFont: { family: "'Inter', system-ui, sans-serif", size: 12 }
      }
    }
  };
}

function withTitle(base, title) {
  base.plugins.title = {
    display: true,
    text: title,
    color: '#D4D4D8',
    font: { size: 13, weight: '600', family: "'Inter', system-ui, sans-serif" },
    padding: { bottom: 12 }
  };
  return base;
}

function cartesianOptions(title, legendLabel) {
  const o = chartDefaults();
  o.plugins.legend.display = !!legendLabel;
  o.plugins.legend.labels = { color: CHART_TEXT, font: CHART_FONT_SM, boxWidth: 10, boxHeight: 10, padding: 12 };
  o.scales = {
    x: {
      grid: { display: false },
      ticks: { color: CHART_TEXT, font: CHART_FONT_SM, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
      border: { display: false }
    },
    y: {
      beginAtZero: true,
      grid: { color: CHART_GRID, drawBorder: false },
      ticks: { color: CHART_TEXT, font: CHART_FONT_SM, precision: 0 },
      border: { display: false }
    }
  };
  return withTitle(o, title);
}

function doughnutOptions(title) {
  const o = chartDefaults();
  o.maintainAspectRatio = true;
  o.aspectRatio = 1;
  o.cutout = '62%';
  o.plugins.legend.display = true;
  o.plugins.legend.position = 'bottom';
  o.plugins.legend.labels = { color: CHART_TEXT, font: CHART_FONT_SM, boxWidth: 10, boxHeight: 10, padding: 12 };
  return withTitle(o, title);
}

/* ---- panel / empty-state HTML ---- */

function chartPanelHtml(id, title, square) {
  return '<div class="chart-panel">' +
    '<h4 class="chart-title">' + title + '</h4>' +
    '<div class="chart-wrap' + (square ? ' chart-wrap-square' : '') + '"><canvas id="' + id + '"></canvas></div>' +
  '</div>';
}

function chartEmptyHtml(title, msg) {
  return '<div class="chart-panel">' +
    '<h4 class="chart-title">' + title + '</h4>' +
    '<div class="chart-empty"><span>' + (msg || 'Not enough data yet. Charts appear as activity grows.') + '</span></div>' +
  '</div>';
}

/* ---- "not enough data" guards (render once any real data exists) ---- */

function hasPositive(arr, key) {
  return Array.isArray(arr) && arr.length > 0 && arr.some(x => Number(x[key]) > 0);
}
function hasTrend(arr) { return hasPositive(arr, 'c'); }
function hasDistribution(arr) { return hasPositive(arr, 'c'); }
function hasTop(arr) { return hasPositive(arr, 'n'); }

function renderChart(id, cfg) {
  const canvas = document.getElementById(id);
  if (!canvas || typeof Chart === 'undefined') return false;
  new Chart(canvas, cfg);
  return true;
}

/* ---- loaders ---- */

async function loadAnalytics(scope) {
  const el = document.getElementById('analyticsCharts');
  if (!el) return;
  showAnalyticsSkeleton(el);
  const data = await fetchAnalytics(scope);
  if (!data) { renderAnalyticsError(el, 'Analytics data could not be loaded.'); return; }
  if (typeof Chart === 'undefined') { renderAnalyticsError(el, 'The chart library failed to load. Check your connection and retry.'); return; }

  const status  = data.status || [];
  const timeline = data.timeline || [];
  const statusOk  = hasDistribution(status);
  const timelineOk = hasTrend(timeline);

  const grid =
    (statusOk    ? chartPanelHtml('statusChart', 'Application Status', true) : chartEmptyHtml('Application Status')) +
    (timelineOk  ? chartPanelHtml('timelineChart', 'Applications Over Time')  : chartEmptyHtml('Applications Over Time'));

  el.innerHTML = '<div class="analytics-chart-grid">' + grid + '</div>';

  if (statusOk) renderChart('statusChart', {
    type: 'doughnut',
    data: {
      labels: status.map(s => s.status),
      datasets: [{ data: status.map(s => s.c), backgroundColor: STATUS_COLORS, borderColor: '#161616', borderWidth: 2 }]
    },
    options: doughnutOptions('Application Status')
  });

  if (timelineOk) renderChart('timelineChart', {
    type: 'line',
    data: {
      labels: timeline.map(t => t.ym),
      datasets: [{
        label: 'Applications', data: timeline.map(t => t.c),
        borderColor: '#22C55E', backgroundColor: 'rgba(34,197,94,0.12)',
        fill: true, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#22C55E'
      }]
    },
    options: cartesianOptions('Applications Over Time')
  });
}

async function loadAdminAnalytics() {
  const el = document.getElementById('analyticsCharts');
  if (!el) return;
  showAnalyticsSkeleton(el);
  const data = await fetchAnalytics('admin');
  if (!data) { renderAnalyticsError(el, 'Analytics data could not be loaded.'); return; }
  const k = data.kpis || {};
  const set = (id, v) => { const n = document.getElementById(id); if (n) n.textContent = (v === undefined || v === null) ? '–' : v; };
  set('kpi-students', k.students); set('kpi-companies', k.companies);
  set('kpi-internships', k.internships); set('kpi-applications', k.applications);
  if (typeof Chart === 'undefined') { renderAnalyticsError(el, 'The chart library failed to load. Check your connection and retry.'); return; }

  const regs   = data.registrations || [];
  const status = data.statusDist || [];
  const tops   = data.topCompanies || [];

  const regOk    = hasTrend(regs);
  const statusOk = hasDistribution(status);
  const topOk    = hasTop(tops);

  const grid =
    (regOk    ? chartPanelHtml('regChart', 'New Student Registrations')         : chartEmptyHtml('New Student Registrations')) +
    (statusOk ? chartPanelHtml('adminStatusChart', 'Status Distribution', true) : chartEmptyHtml('Status Distribution')) +
    (topOk    ? chartPanelHtml('topChart', 'Internships per Company')           : chartEmptyHtml('Internships per Company'));

  el.innerHTML = '<div class="analytics-chart-grid">' + grid + '</div>';

  if (regOk) renderChart('regChart', {
    type: 'bar',
    data: {
      labels: regs.map(r => r.ym),
      datasets: [{ label: 'New students', data: regs.map(r => r.c), backgroundColor: '#22C55E', borderRadius: 6, maxBarThickness: 48 }]
    },
    options: cartesianOptions('New Student Registrations')
  });

  if (statusOk) renderChart('adminStatusChart', {
    type: 'doughnut',
    data: {
      labels: status.map(s => s.status),
      datasets: [{ data: status.map(s => s.c), backgroundColor: STATUS_COLORS, borderColor: '#161616', borderWidth: 2 }]
    },
    options: doughnutOptions('Status Distribution')
  });

  if (topOk) renderChart('topChart', {
    type: 'bar',
    data: {
      labels: tops.map(t => t.name),
      datasets: [{ label: 'Internships', data: tops.map(t => t.n), backgroundColor: '#3B82F6', borderRadius: 6, maxBarThickness: 48 }]
    },
    options: cartesianOptions('Internships per Company')
  });
}

async function loadCompanyAnalytics() {
  const el = document.getElementById('analyticsCharts');
  if (!el) return;
  showAnalyticsSkeleton(el);
  const data = await fetchAnalytics('company');
  if (!data) { renderAnalyticsError(el, 'Analytics data could not be loaded.'); return; }
  if (typeof Chart === 'undefined') { renderAnalyticsError(el, 'The chart library failed to load. Check your connection and retry.'); return; }

  const per      = data.perPosting || [];
  const status   = data.statusDist || [];
  const timeline = data.timeline || [];

  const perOk      = hasTop(per);
  const statusOk   = hasDistribution(status);
  const timelineOk = hasTrend(timeline);

  const grid =
    (perOk      ? chartPanelHtml('perChart', 'Applications per Posting')       : chartEmptyHtml('Applications per Posting')) +
    (statusOk   ? chartPanelHtml('compStatusChart', 'Application Status', true) : chartEmptyHtml('Application Status')) +
    (timelineOk ? chartPanelHtml('compTimelineChart', 'Applications Over Time') : chartEmptyHtml('Applications Over Time'));

  el.innerHTML = '<div class="analytics-chart-grid">' + grid + '</div>';

  if (perOk) renderChart('perChart', {
    type: 'bar',
    data: {
      labels: per.map(p => p.title),
      datasets: [{ label: 'Applications', data: per.map(p => p.n), backgroundColor: '#22C55E', borderRadius: 6, maxBarThickness: 48 }]
    },
    options: cartesianOptions('Applications per Posting')
  });

  if (statusOk) renderChart('compStatusChart', {
    type: 'doughnut',
    data: {
      labels: status.map(s => s.status),
      datasets: [{ data: status.map(s => s.c), backgroundColor: APP_STATUS_COLORS, borderColor: '#161616', borderWidth: 2 }]
    },
    options: doughnutOptions('Application Status')
  });

  if (timelineOk) renderChart('compTimelineChart', {
    type: 'line',
    data: {
      labels: timeline.map(t => t.ym),
      datasets: [{
        label: 'Applications', data: timeline.map(t => t.c),
        borderColor: '#22C55E', backgroundColor: 'rgba(34,197,94,0.12)',
        fill: true, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#22C55E'
      }]
    },
    options: cartesianOptions('Applications Over Time')
  });
}

const analyticsScope = document.body.dataset.analyticsScope || 'student';
function initAnalytics() {
  if (analyticsScope === 'admin') loadAdminAnalytics();
  else if (analyticsScope === 'company') loadCompanyAnalytics();
  else loadAnalytics('student');
}
window.retryAnalytics = initAnalytics;
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAnalytics);
} else {
  initAnalytics();
}