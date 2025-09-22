<x-app-layout>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
    <h1 class="h4 m-0">Mi Panel</h1>
    <a href="{{ route('beneficiarios.create') }}" class="btn btn-success btn-lg">
      <i class="bi bi-plus-circle"></i>
      Capturar beneficiario
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Pacientes activos</div>
              <div class="display-6" id="kpi-asignados">0</div>
            </div>
            <i class="bi bi-people fs-2 text-primary"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Sesiones esta semana</div>
              <div class="display-6" id="kpi-sesiones">0</div>
            </div>
            <i class="bi bi-calendar-week fs-2 text-success"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">PrÃƒÂ³ximas citas Ã¢â€°Â¤ 7 dÃƒÂ­as</div>
              <div class="display-6" id="kpi-proximas">0</div>
            </div>
            <i class="bi bi-alarm fs-2 text-warning"></i>
          </div>
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

<script>
  document.addEventListener('DOMContentLoaded', async () => {
    const r = await fetch('{{ route('s360.enc360.dash') }}');
    if (!r.ok) return;
    const data = await r.json();
    document.getElementById('kpi-asignados').textContent = data.totalAsignados ?? 0;
    document.getElementById('kpi-sesiones').textContent = data.sesionesSemana ?? 0;
    document.getElementById('kpi-proximas').textContent = data.proximosAVencer ?? 0;
    const top = data.topPsicologos || [];
    const ul = document.getElementById('lista-top');
    ul.innerHTML = top.map(i => `<li class="list-group-item d-flex justify-content-between"><span>${i.name}</span><span class="badge bg-secondary">${i.cargas}</span></li>`).join('');
  });
</script>
</x-app-layout>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', async () => {
    try {
      const ult = await fetch('{{ route('s360.enc360.sesiones.latest') }}');
      if (ult.ok) {
        const data = await ult.json();
        document.getElementById('lista-sesiones').innerHTML = (data.items||[]).map(s => {
          return `<li class="list-group-item"><div class="d-flex justify-content-between"><span>${s.beneficiario}</span><span class="text-muted">${s.session_date}</span></div><div class="small text-muted">${s.psicologo}${s.is_first?' Ã¢â‚¬Â¢ Primera':''}</div></li>`;
        }).join('');
      }
      const prox = await fetch('{{ route('s360.enc360.citas.upcoming') }}');
      if (prox.ok) {
        const data = await prox.json();
        document.getElementById('tabla-citas').innerHTML = (data.items||[]).map(r => `<tr><td>${r.beneficiario}</td><td>${r.psicologo}</td><td>${r.next_session_date}</td></tr>`).join('');
      }
    } catch (e) { /* noop */ }
  });
</script>
@endpush


