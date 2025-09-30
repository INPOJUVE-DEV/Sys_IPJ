import { onMounted } from './util/dom';
import Chart from 'chart.js/auto';

let chartRefs = {};
function destroyCharts() {
  Object.values(chartRefs).forEach(ch => { try { ch.destroy(); } catch {} });
  chartRefs = {};
}

async function fetchJSON(url) {
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  if (!res.ok) throw new Error('HTTP '+res.status);
  return await res.json();
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function renderChart(ctxId, type, labels, data, options = {}) {
  const ctx = document.getElementById(ctxId);
  if (!ctx) return null;
  const ds = [{ label: options.label || '', data, backgroundColor: options.backgroundColor || '#338C36', borderColor: options.borderColor || '#338C36', tension: 0.2 }];
  const ch = new Chart(ctx, { type, data: { labels, datasets: ds }, options: options.options || { responsive: true, plugins: { legend: { display: !!options.label } } } });
  chartRefs[ctxId] = ch;
  return ch;
}

async function renderKpis(url) {
  try {
    destroyCharts();
    const data = await fetchJSON(url);

    // Admin / Encargado structure
    if (data.totals) {
      setText('kpiTotal', data.totals.total);
      if (data.today) {
        setText('kpiTodayTotal', data.today.total ?? '0');
      }
      if (data.week && Object.prototype.hasOwnProperty.call(data.week, 'total')) {
        setText('kpiWeekTotal', data.week.total ?? '0');
      }
      if (data.last30Days && Object.prototype.hasOwnProperty.call(data.last30Days, 'total')) {
        setText('kpiLast30Total', data.last30Days.total ?? '0');
      }
      if (data.byMunicipio) renderChart('chartByMunicipio', 'bar', data.byMunicipio.labels, data.byMunicipio.data, { label: 'Por municipio' });
      if (data.bySeccional) renderChart('chartBySeccional', 'bar', data.bySeccional.labels, data.bySeccional.data, { label: 'Por seccional' });
      if (data.byCapturista) renderChart('chartByCapturista', 'bar', data.byCapturista.labels, data.byCapturista.data, { label: 'Por capturista' });
      if (data.week) renderChart('chartWeek', 'line', data.week.labels, data.week.data, { label: 'Semana' });
      if (data.last30Days) renderChart('chart30', 'line', data.last30Days.labels, data.last30Days.data, { label: '30 días' });
    } else {
      // Capturista personal
      setText('kpiToday', data.today);
      setText('kpiWeek', data.week);
      setText('kpi30', data.last30Days);

      if (Array.isArray(data.ultimos)) {
        const list = document.getElementById('ultimosList');
        if (list) {
          list.innerHTML = '';
          data.ultimos.forEach(it => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between';
            const created = (it.created_at || '').toString().replace('T',' ').substring(0,16);
            li.innerHTML = `<span>${it.folio_tarjeta || it.id}</span><small class="text-muted">${created}</small>`;
            list.appendChild(li);
          });
        }
      }
      if (data.series) renderChart('chartMine', 'line', data.series.labels, data.series.data, { label: '30 días' });
    }
  } catch (e) {
    // eslint-disable-next-line no-console
    console.error('KPIs error', e);
  }
}

function serializeForm(form) {
  const fd = new FormData(form);
  const params = new URLSearchParams();
  for (const [k,v] of fd.entries()) {
    if (v !== '') params.append(k, v);
  }
  return params.toString();
}

onMounted(async () => {
  const kpisEl = document.querySelector('[data-kpis-url]');
  if (!kpisEl) return;
  const baseUrl = kpisEl.getAttribute('data-kpis-url');
  await renderKpis(baseUrl);

  const form = document.getElementById('kpiFilters');
  const exportBtn = document.getElementById('exportCsvBtn');
  const exportBase = kpisEl.getAttribute('data-export-url');
  const updateExportHref = () => {
    if (!exportBtn || !exportBase) return;
    const qs = form ? serializeForm(form) : '';
    exportBtn.href = qs ? `${exportBase}?${qs}` : exportBase;
  };
  updateExportHref();
  if (form) {
    const handler = async (e) => {
      e && e.preventDefault();
      const qs = serializeForm(form);
      const url = qs ? `${baseUrl}?${qs}` : baseUrl;
      await renderKpis(url);
      updateExportHref();
    };
    form.addEventListener('submit', handler);
    form.querySelectorAll('input,select').forEach(el => {
      el.addEventListener('change', handler);
    });
  }
});
