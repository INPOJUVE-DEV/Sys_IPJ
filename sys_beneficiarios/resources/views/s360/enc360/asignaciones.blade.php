<x-app-layout>
<div class="container py-4">
  <h1 class="h5 mb-3">Asignaciones</h1>
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-6">
          <label class="form-label small text-uppercase text-muted">Buscar beneficiario</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-secondary text-white"><i class="bi bi-search"></i></span>
            <input id="beneficiarioSearch" type="search" class="form-control" placeholder="Nombre, apellidos o correo">
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <label class="form-label small text-uppercase text-muted">Estado</label>
          <select id="f-status" class="form-select form-select-sm">
            <option value="active" selected>Activas</option>
            <option value="inactive">Inactivas</option>
            <option value="all">Todas</option>
          </select>
        </div>
        <div class="col-6 col-lg-3">
          <label class="form-label small text-uppercase text-muted">Municipio ID</label>
          <input id="f-mun" type="number" class="form-control form-control-sm" placeholder="Opcional">
        </div>
      </div>
      <div class="mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span class="small text-uppercase text-muted">Psicólogos disponibles</span>
          <button class="btn btn-sm btn-outline-light" type="button" id="refreshPsicologos"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar</button>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2" id="psicologoChips">
          <button type="button" class="btn btn-sm btn-outline-secondary active" data-psi="">Todos</button>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Beneficiario</th>
              <th>PsicÃ³logo</th>
              <th>Municipio</th>
              <th>Asignada</th>
              <th>Estado</th>
              <th style="width:140px">Acciones</th>
            </tr>
          </thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
      <div id="pager" class="small text-muted"></div>
    </div>
  </div>
</div>

<!-- Modal cambiar psicÃ³logo -->
<div class="modal fade" id="modalReassign" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Cambiar psicÃ³logo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="m-ben">
        <input type="hidden" id="m-current">
        <label class="form-label">Nuevo psicÃ³logo</label>
        <select id="m-psi" class="form-select"></select>
        <div class="small text-danger mt-2" id="m-err"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button id="btn-reassign" class="btn btn-primary">Guardar</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
let reloadAssignments = () => {};

async function loadPsicologosOptions(current = '') {
    const sel = document.getElementById('m-psi');
    if (!sel) return;
    sel.innerHTML = '<option value="">Cargando…</option>';
    try {
        const res = await fetch('/s360/enc360/psicologos/list');
        const data = await res.json();
        const options = (data.data || []).map(r => {
            const carga = typeof r.cargas !== 'undefined' ? ` — ${r.cargas}` : '';
            return `<option value="${r.id}" ${String(current) === String(r.id) ? 'selected' : ''}>${r.name} (${r.email})${carga}</option>`;
        }).join('');
        sel.innerHTML = '<option value="">Seleccione…</option>' + options;
    } catch (error) {
        sel.innerHTML = '<option value="">Error al cargar psicólogos</option>';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('rows');
    const pager = document.getElementById('pager');
    const searchInput = document.getElementById('beneficiarioSearch');
    const statusSelect = document.getElementById('f-status');
    const municipioInput = document.getElementById('f-mun');
    const chipContainer = document.getElementById('psicologoChips');
    const refreshBtn = document.getElementById('refreshPsicologos');
    let selectedPsychologist = '';
    let currentRows = [];
    let pagination = { current_page: 1, last_page: 1, total: 0 };
    let debounceTimer = null;

    const normalize = (value) => (value || '').toString().trim().toLowerCase();
    const debounce = (fn, delay = 350) => (...args) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fn(...args), delay);
    };

    const buildRowHtml = (row) => {
        const nombre = `${row.ben_nombre || ''} ${row.ben_apellido_paterno || ''} ${row.ben_apellido_materno || ''}`.replace(/\s+/g, ' ').trim() || 'Sin nombre';
        const estado = row.active ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-secondary">Inactiva</span>';
        const psicologo = row.psicologo_name || '<span class="text-muted">Sin asignar</span>';
        const fecha = (row.assigned_at || '').toString().substring(0, 10);
        return `<tr>
      <td>${nombre}</td>
      <td>${psicologo}</td>
      <td>${row.municipio_id ?? ''}</td>
      <td>${fecha}</td>
      <td>${estado}</td>
      <td>
        <button class="btn btn-sm btn-outline-primary" data-action="reassign" data-ben="${row.beneficiario_id}" data-currentpsi="${row.psicologo_id || ''}">Cambiar</button>
      </td>
    </tr>`;
    };

    function attachReassign() {
        document.querySelectorAll('button[data-action="reassign"]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const ben = btn.getAttribute('data-ben');
                const current = btn.getAttribute('data-currentpsi') || '';
                document.getElementById('m-ben').value = ben;
                document.getElementById('m-current').value = current;
                await loadPsicologosOptions(current);
                new bootstrap.Modal(document.getElementById('modalReassign')).show();
            });
        });
    }

    function renderRows() {
        if (!tbody) return;
        const query = normalize(searchInput ? searchInput.value : '');
        const filtered = currentRows.filter(row => {
            const fullName = normalize(`${row.ben_nombre || ''} ${row.ben_apellido_paterno || ''} ${row.ben_apellido_materno || ''}`);
            const psychologist = normalize(row.psicologo_name || '');
            return !query || fullName.includes(query) || psychologist.includes(query);
        });
        if (!filtered.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay coincidencias con los filtros seleccionados.</td></tr>';
        } else {
            tbody.innerHTML = filtered.map(buildRowHtml).join('');
        }
        attachReassign();
        if (pager) {
            const pageText = `Página ${pagination.current_page ?? 1} de ${pagination.last_page ?? 1}`;
            const totalText = `${filtered.length} de ${pagination.total ?? filtered.length} resultados`;
            pager.textContent = `${pageText} • ${totalText}`;
        }
    }

    async function fetchAssignments(page = 1) {
        const params = new URLSearchParams({ status: statusSelect ? statusSelect.value : 'active', page });
        const municipio = (municipioInput ? municipioInput.value : '').trim();
        if (municipio) params.append('municipio_id', municipio);
        if (selectedPsychologist) params.append('psicologo_id', selectedPsychologist);
        if (pager) {
            pager.innerHTML = '<div class="d-flex align-items-center"><div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando…</div>';
        }
        try {
            const res = await fetch(`/s360/enc360/pacientes?${params.toString()}`);
            const data = await res.json();
            currentRows = data.data || [];
            pagination = data;
        } catch (error) {
            currentRows = [];
            pagination = { current_page: 1, last_page: 1, total: 0 };
        }
        renderRows();
    }

    async function renderPsychologistChips() {
        if (!chipContainer) return;
        chipContainer.innerHTML = '<div class="d-flex align-items-center text-muted small"><div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando psicólogos…</div>';
        try {
            const res = await fetch('/s360/enc360/psicologos/list');
            const data = await res.json();
            const items = data.data || data.items || [];
            const fragment = document.createDocumentFragment();
            const allBtn = document.createElement('button');
            allBtn.type = 'button';
            allBtn.dataset.psi = '';
            allBtn.className = `btn btn-sm btn-outline-secondary ${selectedPsychologist ? '' : 'active'}`;
            allBtn.textContent = 'Todos';
            fragment.appendChild(allBtn);
            items.forEach(item => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.psi = item.id;
                btn.className = `btn btn-sm btn-outline-secondary ${String(item.id) === String(selectedPsychologist) ? 'active' : ''}`;
                const carga = typeof item.cargas !== 'undefined' ? ` · ${item.cargas} pacientes` : '';
                btn.textContent = `${item.name || 'Psicólogo'}${carga}`;
                fragment.appendChild(btn);
            });
            chipContainer.innerHTML = '';
            chipContainer.appendChild(fragment);
        } catch (error) {
            chipContainer.innerHTML = '<div class="text-warning small">No se pudieron cargar los psicólogos disponibles.</div>';
        }
    }

    if (chipContainer) {
        chipContainer.addEventListener('click', (event) => {
            const target = event.target.closest('button[data-psi]');
            if (!target) return;
            chipContainer.querySelectorAll('button[data-psi]').forEach(btn => btn.classList.remove('active'));
            target.classList.add('active');
            selectedPsychologist = target.dataset.psi || '';
            fetchAssignments();
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => renderPsychologistChips());
    }
    statusSelect?.addEventListener('change', () => fetchAssignments());
    municipioInput?.addEventListener('input', debounce(() => fetchAssignments()));
    searchInput?.addEventListener('input', () => renderRows());

    const params = new URLSearchParams(location.search);
    if (params.get('psicologo_id')) {
        selectedPsychologist = params.get('psicologo_id');
    }

    reloadAssignments = fetchAssignments;
    renderPsychologistChips();
    fetchAssignments();
});

document.addEventListener('DOMContentLoaded', () => {
    const btnRe = document.getElementById('btn-reassign');
    if (!btnRe) return;
    btnRe.addEventListener('click', async () => {
        const ben = document.getElementById('m-ben').value;
        const psi = document.getElementById('m-psi').value;
        const current = document.getElementById('m-current').value;
        const err = document.getElementById('m-err');
        err.textContent = '';
        if (!psi) {
            err.textContent = 'Seleccione un psicólogo';
            return;
        }
        if (current && String(psi) === String(current)) {
            err.textContent = 'Seleccione un psicólogo diferente al actual';
            return;
        }
        btnRe.setAttribute('disabled', 'disabled');
        const payload = { psicologo_id: psi };
        try {
            const res = await fetch(`/s360/enc360/assign/${ben}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({ message: 'Error al guardar' }));
                err.textContent = data.message || 'Error al guardar';
                btnRe.removeAttribute('disabled');
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('modalReassign')).hide();
            window.showToast?.('Asignación actualizada', 'success');
            btnRe.removeAttribute('disabled');
            reloadAssignments();
        } catch (error) {
            err.textContent = 'No fue posible actualizar la asignación.';
            btnRe.removeAttribute('disabled');
        }
    });
});
</script>
@endpush
</x-app-layout>



