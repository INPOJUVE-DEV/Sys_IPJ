<x-app-layout>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
    <h1 class="h4 m-0">Mi Panel</h1>
    <a href="{{ route('beneficiarios.create') }}" class="btn btn-success btn-lg">
      <i class="bi bi-plus-circle"></i>
      Capturar beneficiario
    </a>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-semibold">Distribución de pacientes por psicólogo</span>
      <span class="text-muted small" id="workloadSummary"></span>
    </div>
    <div class="card-body">
      <div class="position-relative" style="min-height:220px;">
        <canvas id="workloadChart" height="220" aria-label="Pacientes por psicólogo" role="img"></canvas>
        <div id="workloadFallback" class="text-muted small text-center position-absolute top-50 start-50 translate-middle d-none">
          Sin datos suficientes para generar el gráfico.
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-white">
          <div class="d-flex gap-2 align-items-center">
            <span class="fw-semibold">ÃƒÅ¡ltimas sesiones</span>
            <input type="text" class="form-control form-control-sm" placeholder="Beneficiario" id="filtro-sesiones">
            <select class="form-select form-select-sm" id="filtro-sesiones-select">
              <option value="">Todos</option>
            </select>
          </div>
        </div>
        <div class="card-body" style="max-height:420px; overflow:auto;">
          <ul class="list-group list-group-flush" id="lista-sesiones"></ul>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-white">
          <div class="d-flex gap-2 align-items-center">
            <span class="fw-semibold">PrÃƒÂ³ximas citas</span>
            <input type="text" class="form-control form-control-sm" placeholder="Beneficiario" id="filtro-citas">
            <select class="form-select form-select-sm" id="filtro-citas-select">
              <option value="">Todos</option>
            </select>
          </div>
        </div>
        <div class="card-body" style="max-height:420px; overflow:auto;">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr><th>Beneficiario</th><th>PsicÃƒÂ³logo</th><th>Fecha</th></tr>
            </thead>
            <tbody id="tabla-citas"></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-white">
          <div class="d-flex gap-2 align-items-center">
            <span class="fw-semibold">PsicÃƒÂ³logos con mÃƒÂ¡s carga</span>
            <input type="text" class="form-control form-control-sm" placeholder="Beneficiario" id="filtro-top">
            <select class="form-select form-select-sm" id="filtro-top-select">
              <option value="">Todos</option>
            </select>
          </div>
        </div>
        <div class="card-body" style="max-height:420px; overflow:auto;">
          <ul class="list-group list-group-flush" id="lista-top"></ul>
        </div>
      </div>
    </div>
  </div>
</div>

</x-app-layout>

@push('scripts')
<script type="module">
let workloadChartInstance = null;

document.addEventListener('DOMContentLoaded', async () => {
    const workloadCanvas = document.getElementById('workloadChart');
    const workloadFallback = document.getElementById('workloadFallback');
    const workloadSummary = document.getElementById('workloadSummary');
    const listaTop = document.getElementById('lista-top');
    const listaSesiones = document.getElementById('lista-sesiones');
    const tablaCitas = document.getElementById('tabla-citas');

    let ChartModule;
    try {
        ChartModule = await import('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js');
    } catch (error) {
        console.warn('No se pudo cargar Chart.js', error);
    }

    try {
        const dashRes = await fetch('{{ route('s360.enc360.dash') }}');
        if (dashRes.ok) {
            const dashData = await dashRes.json();
            const top = dashData.topPsicologos || [];
            if (listaTop) {
                listaTop.innerHTML = top.length
                    ? top.map(i => `<li class="list-group-item d-flex justify-content-between align-items-center"><span>${i.name}</span><span class="badge bg-secondary">${i.cargas}</span></li>`).join('')
                    : '<li class="list-group-item text-muted">Sin datos registrados.</li>';
            }
            if (workloadSummary) {
                const parts = [];
                if (typeof dashData.totalAsignados !== 'undefined') parts.push(`Pacientes activos: ${dashData.totalAsignados}`);
                if (typeof dashData.sesionesSemana !== 'undefined') parts.push(`Sesiones semana: ${dashData.sesionesSemana}`);
                if (typeof dashData.proximosAVencer !== 'undefined') parts.push(`Próximas citas (≤7 días): ${dashData.proximosAVencer}`);
                workloadSummary.textContent = parts.join(' • ');
            }
            if (ChartModule && workloadCanvas && top.length) {
                const Chart = ChartModule.Chart || ChartModule.default;
                workloadFallback?.classList.add('d-none');
                workloadCanvas.classList.remove('d-none');
                workloadChartInstance?.destroy?.();
                workloadChartInstance = new Chart(workloadCanvas, {
                    type: 'bar',
                    data: {
                        labels: top.map(item => item.name),
                        datasets: [{
                            label: 'Pacientes asignados',
                            data: top.map(item => item.cargas),
                            backgroundColor: '#0d6efd',
                            borderRadius: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                                grid: { color: 'rgba(255,255,255,0.05)' },
                            },
                            x: {
                                grid: { display: false },
                            },
                        },
                        plugins: {
                            legend: { display: false },
                        },
                    },
                });
            } else {
                if (workloadCanvas) workloadCanvas.classList.add('d-none');
                if (workloadFallback) {
                    workloadFallback.textContent = top.length ? 'No se pudo generar el gráfico.' : 'Sin datos suficientes para generar el gráfico.';
                    workloadFallback.classList.remove('d-none');
                }
            }
        }
    } catch (error) {
        if (workloadCanvas) workloadCanvas.classList.add('d-none');
        if (workloadFallback) {
            workloadFallback.textContent = 'No se pudo cargar la información de asignaciones.';
            workloadFallback.classList.remove('d-none');
        }
        console.error('Error cargando dashboard', error);
    }

    try {
        const ult = await fetch('{{ route('s360.enc360.sesiones.latest') }}');
        if (ult.ok) {
            const data = await ult.json();
            if (listaSesiones) {
                listaSesiones.innerHTML = (data.items || []).map(s => {
                    return `<li class="list-group-item"><div class="d-flex justify-content-between"><span>${s.beneficiario}</span><span class="text-muted">${s.session_date}</span></div><div class="small text-muted">${s.psicologo}${s.is_first ? ' • Primera' : ''}</div></li>`;
                }).join('');
            }
        }
    } catch (error) {
        console.error('Error cargando sesiones', error);
    }

    try {
        const prox = await fetch('{{ route('s360.enc360.citas.upcoming') }}');
        if (prox.ok) {
            const data = await prox.json();
            if (tablaCitas) {
                tablaCitas.innerHTML = (data.items || []).map(r => `<tr><td>${r.beneficiario}</td><td>${r.psicologo}</td><td>${r.next_session_date}</td></tr>`).join('');
            }
        }
    } catch (error) {
        console.error('Error cargando citas', error);
    }
});
</script>
@endpush


