import { onMounted } from './util/dom';
import Chart from 'chart.js/auto';

let chartRef = null;

async function fetchJSON(url) {
  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function serializeForm(form) {
  const fd = new FormData(form);
  const params = new URLSearchParams();
  for (const [key, value] of fd.entries()) {
    if (value !== '') params.append(key, value);
  }
  return params.toString();
}

function renderChart(payload) {
  const canvas = document.getElementById('indicatorsChart');
  if (!canvas || !payload?.daily) return;

  if (chartRef) {
    try { chartRef.destroy(); } catch {}
    chartRef = null;
  }

  chartRef = new Chart(canvas, {
    type: 'line',
    data: {
      labels: payload.daily.labels,
      datasets: [
        {
          label: 'Beneficiarios',
          data: payload.daily.beneficiarios,
          borderColor: '#0d6efd',
          backgroundColor: 'rgba(13, 110, 253, 0.12)',
          tension: 0.25,
        },
        {
          label: 'Inscripciones',
          data: payload.daily.inscripciones,
          borderColor: '#198754',
          backgroundColor: 'rgba(25, 135, 84, 0.12)',
          tension: 0.25,
        },
        {
          label: 'Eventos',
          data: payload.daily.eventos,
          borderColor: '#fd7e14',
          backgroundColor: 'rgba(253, 126, 20, 0.12)',
          tension: 0.25,
        },
      ],
    },
    options: {
      responsive: true,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: {
          display: true,
        },
      },
    },
  });
}

async function renderIndicators(url) {
  const payload = await fetchJSON(url);
  setText('indicatorBeneficiarios', payload.totals?.beneficiarios ?? 0);
  setText('indicatorInscripciones', payload.totals?.inscripciones ?? 0);
  setText('indicatorEventos', payload.totals?.eventos ?? 0);

  if (payload.range) {
    setText('indicatorRange', `${payload.range.from} a ${payload.range.to}`);
  }

  renderChart(payload);
}

onMounted(async () => {
  const container = document.querySelector('[data-indicators-url]');
  if (!container) return;

  const baseUrl = container.getAttribute('data-indicators-url');
  const form = document.getElementById('indicatorFilters');

  const load = async (event) => {
    event?.preventDefault();
    const qs = form ? serializeForm(form) : '';
    const url = qs ? `${baseUrl}?${qs}` : baseUrl;
    await renderIndicators(url);
  };

  await load();

  if (form) {
    form.addEventListener('submit', load);
    form.querySelectorAll('input, select').forEach((field) => field.addEventListener('change', load));
  }
});
