<x-app-layout>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-3">
    <h1 class="h4 m-0">Mi Panel</h1>
    <div class="d-flex flex-column flex-sm-row gap-2">
      <a href="{{ route('beneficiarios.create') }}" class="btn btn-success btn-lg">
        <i class="bi bi-plus-circle"></i>
        Capturar beneficiario
      </a>
      <a href="{{ route('s360.enc360.asignaciones') }}" class="btn btn-primary btn-lg">
        <i class="bi bi-person-plus-fill"></i>
        Asignar paciente
      </a>
    </div>
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

  <div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <span class="fw-semibold">Actividad reciente</span>
        <p class="text-muted small mb-0">Seguimiento de las asignaciones y sesiones más recientes.</p>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="recentActivityRefresh">
        <i class="bi bi-arrow-repeat me-1"></i>Actualizar
      </button>
    </div>
    <div class="card-body p-0">
      <div id="recentActivityLoading" class="px-3 py-3 border-bottom text-muted small d-flex align-items-center gap-2">
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        <span>Cargando actividad…</span>
      </div>
      <div class="list-group list-group-flush" id="recentActivityList"></div>
      <div id="recentActivityEmpty" class="text-muted small text-center py-3 d-none">No hay actividad reciente registrada.</div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-white">
          <div class="d-flex gap-2 align-items-center">
            <span class="fw-semibold">Últimas sesiones</span>
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
            <span class="fw-semibold">Próximas citas</span>
            <input type="text" class="form-control form-control-sm" placeholder="Beneficiario" id="filtro-citas">
            <select class="form-select form-select-sm" id="filtro-citas-select">
              <option value="">Todos</option>
            </select>
          </div>
        </div>
        <div class="card-body" style="max-height:420px; overflow:auto;">
          <div id="citasEmpty" class="text-muted small text-center py-3 d-none">Sin próximas citas registradas.</div>
          <div id="tabla-citas" class="d-grid gap-3"></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-white">
          <div class="d-flex gap-2 align-items-center">
            <span class="fw-semibold">Psicólogos con más carga</span>
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
    const citasEmpty = document.getElementById('citasEmpty');
    const activityList = document.getElementById('recentActivityList');
    const activityEmpty = document.getElementById('recentActivityEmpty');
    const activityLoading = document.getElementById('recentActivityLoading');
    const activityRefresh = document.getElementById('recentActivityRefresh');
    const beneficiaryDetailTemplate = @json(route('beneficiarios.edit', ['beneficiario' => '__ID__']));
    const parseDateValue = (value) => {
        if (!value) return null;
        const normalized = value.includes('T') ? value : `${value}T00:00:00`;
        const date = new Date(normalized);
        return Number.isNaN(date.getTime()) ? null : date;
    };
    const dateFormatter = (() => {
        try {
            return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
        } catch (error) {
            console.warn('No se pudo crear el formateador de fechas', error);
            return null;
        }
    })();
    const formatDateValue = (value) => {
        const date = parseDateValue(value);
        if (!date) return 'Sin fecha';
        return dateFormatter ? dateFormatter.format(date) : date.toLocaleString();
    };
    const buildBeneficiaryUrl = (id) => {
        if (!id) return null;
        return beneficiaryDetailTemplate.replace('__ID__', id);
    };
    const renderActivityItems = (items) => {
        if (!activityList) return;
        if (!items.length) {
            activityList.innerHTML = '';
            activityEmpty?.classList.remove('d-none');
            return;
        }
        activityEmpty?.classList.add('d-none');
        activityList.innerHTML = items.map(item => {
            const tag = item.url ? 'a' : 'div';
            const typeLabel = item.type === 'assignment' ? 'Asignación' : 'Sesión';
            const badgeClass = item.type === 'assignment' ? 'bg-primary' : 'bg-success';
            const hrefAttr = item.url ? ` href="${item.url}"` : '';
            const description = item.description ? `${typeLabel} • ${item.description}` : typeLabel;
            return `<${tag} class="list-group-item list-group-item-action d-flex gap-3 align-items-start"${hrefAttr}>
                <div class="pt-1">
                    <span class="badge rounded-pill ${badgeClass} text-white"><i class="bi ${item.icon}"></i></span>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <span class="fw-semibold">${item.label}</span>
                        <span class="small text-muted">${item.dateLabel}</span>
                    </div>
                    <div class="small text-muted">${description}</div>
                </div>
            </${tag}>`;
        }).join('');
    };
    const loadRecentActivity = async () => {
        if (!activityList) return;
        if (activityLoading) activityLoading.classList.remove('d-none');
        activityList.innerHTML = '';
        activityEmpty?.classList.add('d-none');
        try {
            const [assignRes, sessionsRes] = await Promise.all([
                fetch('{{ route('s360.enc360.patients') }}?status=active&per_page=10', { headers: { 'Accept': 'application/json' } }),
                fetch('{{ route('s360.enc360.sesiones.latest') }}', { headers: { 'Accept': 'application/json' } }),
            ]);
            const assignmentsData = assignRes.ok ? await assignRes.json() : { data: [] };
            const sessionsData = sessionsRes.ok ? await sessionsRes.json() : { items: [] };
            const items = [];
            (assignmentsData.data || assignmentsData.items || []).forEach(item => {
                const nombre = [item.ben_nombre, item.ben_apellido_paterno, item.ben_apellido_materno].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim() || 'Beneficiario sin nombre';
                const assignedAt = item.assigned_at || item.created_at || null;
                const date = parseDateValue(assignedAt);
                items.push({
                    type: 'assignment',
                    label: nombre,
                    description: item.psicologo_name ? `Asignado a ${item.psicologo_name}` : 'Asignación registrada',
                    dateValue: date ? date.getTime() : 0,
                    dateLabel: formatDateValue(assignedAt),
                    icon: 'bi-person-plus-fill',
                    url: buildBeneficiaryUrl(item.beneficiario_id),
                });
            });
            (sessionsData.items || []).forEach(session => {
                const date = parseDateValue(session.session_date);
                items.push({
                    type: 'session',
                    label: session.beneficiario || 'Beneficiario sin nombre',
                    description: `${session.psicologo || 'Sin psicólogo'}${session.is_first ? ' • Primera sesión' : ''}`,
                    dateValue: date ? date.getTime() : 0,
                    dateLabel: formatDateValue(session.session_date),
                    icon: 'bi-journal-check',
                    url: buildBeneficiaryUrl(session.beneficiario_id),
                });
            });
            items.sort((a, b) => b.dateValue - a.dateValue);
            renderActivityItems(items.slice(0, 8));
        } catch (error) {
            console.error('Error cargando actividad reciente', error);
            if (activityList) {
                activityList.innerHTML = '<div class="list-group-item small text-warning">No se pudo cargar la actividad reciente.</div>';
            }
            activityEmpty?.classList.add('d-none');
        } finally {
            activityLoading?.classList.add('d-none');
        }
    };
    activityRefresh?.addEventListener('click', () => loadRecentActivity());
    loadRecentActivity();
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
                const buildCard = (item = {}) => {
                    const beneficiario = item.beneficiario || 'Beneficiario sin nombre';
                    const psicologo = item.psicologo || 'Sin asignar';
                    const fecha = item.next_session_date || 'Sin fecha';
                    return `<div class="card bg-dark border border-white text-white shadow-sm">
                        <div class="card-body d-flex flex-column gap-2">
                            <div>
                                <h3 class="h6 text-white mb-1">${beneficiario}</h3>
                                <div class="small text-white-50"><i class="bi bi-person-video3 me-1"></i>${psicologo}</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-calendar-event"></i>
                                <span>${fecha}</span>
                            </div>
                        </div>
                    </div>`;
                };
                const cards = (data.items || []).map(buildCard).join('');
                tablaCitas.innerHTML = cards;
                if (!cards) {
                    tablaCitas.classList.add('d-none');
                    citasEmpty?.classList.remove('d-none');
                } else {
                    tablaCitas.classList.remove('d-none');
                    citasEmpty?.classList.add('d-none');
                }
            }
        }
    } catch (error) {
        console.error('Error cargando citas', error);
    }
});
</script>
@endpush


