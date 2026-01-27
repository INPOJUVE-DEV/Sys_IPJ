import { onMounted } from './util/dom';
import Chart from 'chart.js/auto';

let chartRefs = {};
function destroyCharts() {
  Object.values(chartRefs).forEach(ch => { try { ch.destroy(); } catch {} });
  chartRefs = {};
}

async function fetchJSON(url) {
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  if (!res.ok) throw new Error('HTTP ' + res.status);
  return await res.json();
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function renderChart(ctxId, type, labels, data, options = {}) {
  const ctx = document.getElementById(ctxId);
  if (!ctx) return null;
  const ds = [{
    label: options.label || '',
    data,
    backgroundColor: options.backgroundColor || '#0d6efd',
    borderColor: options.borderColor || '#0d6efd',
    tension: 0.2,
  }];
  const ch = new Chart(ctx, {
    type,
    data: { labels, datasets: ds },
    options: options.options || { responsive: true, plugins: { legend: { display: !!options.label } } },
  });
  chartRefs[ctxId] = ch;
  return ch;
}

function serializeForm(form) {
  const fd = new FormData(form);
  const params = new URLSearchParams();
  for (const [k, v] of fd.entries()) {
    if (v !== '') params.append(k, v);
  }
  return params.toString();
}

async function renderKpis(url) {
  try {
    destroyCharts();
    const data = await fetchJSON(url);

    setText('kpiTotal', data.totals?.total ?? 0);
    setText('kpiCurrentMonth', data.totals?.currentMonth ?? 0);
    if (data.range) {
      setText('kpiRange', `${data.range.from} - ${data.range.to}`);
    }

    if (data.monthly) {
      renderChart('chartMonthly', 'line', data.monthly.labels, data.monthly.data, { label: 'Mensual' });
    }
    if (data.byPrograma) {
      renderChart('chartByPrograma', 'bar', data.byPrograma.labels, data.byPrograma.data, { label: 'Por programa' });
    }
  } catch (e) {
    // eslint-disable-next-line no-console
    console.error('KPIs error', e);
  }
}

onMounted(async () => {
  const kpisEl = document.querySelector('[data-kpis-url]');
  if (!kpisEl) return;
  const baseUrl = kpisEl.getAttribute('data-kpis-url');
  await renderKpis(baseUrl);

  const form = document.getElementById('kpiFilters');
  if (form) {
    const handler = async (e) => {
      e && e.preventDefault();
      const qs = serializeForm(form);
      const url = qs ? `${baseUrl}?${qs}` : baseUrl;
      await renderKpis(url);
    };
    form.addEventListener('submit', handler);
    form.querySelectorAll('input,select').forEach(el => el.addEventListener('change', handler));
  }
});
