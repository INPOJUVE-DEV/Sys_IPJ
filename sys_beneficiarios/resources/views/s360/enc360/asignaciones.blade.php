<x-app-layout>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div>
      <h1 class="h4 mb-1">Asignaciones</h1>
      <p class="text-muted small mb-0">Gestiona las asignaciones activas y utiliza el asistente para vincular nuevos beneficiarios.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button class="btn btn-primary btn-sm" type="button" data-wizard-start>
        <i class="bi bi-person-plus-fill me-1"></i>Asignar paciente
      </button>
      <button class="btn btn-outline-light btn-sm" type="button" id="refreshAssignments"><i class="bi bi-arrow-repeat me-1"></i>Actualizar lista</button>
    </div>
  </div>

  <div class="card mb-4" id="assignmentWizard"
       data-assign-url="{{ route('s360.enc360.assign') }}"
       data-beneficiario-search-url="{{ route('beneficiarios.index') }}"
       data-psicologos-url="{{ route('s360.enc360.psicologos.list') }}">
    <div class="card-header bg-white">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
          <span class="fw-semibold">Flujo guiado de asignación</span>
          <p class="text-muted small mb-0">Selecciona un beneficiario disponible y asigna al psicólogo con la carga adecuada.</p>
        </div>
        <div class="d-flex align-items-center gap-2 wizard-indicator">
          <span class="badge rounded-pill bg-primary text-white" data-step-indicator="1">1</span>
          <span class="text-muted small">Beneficiario</span>
          <i class="bi bi-arrow-right text-muted"></i>
          <span class="badge rounded-pill bg-secondary bg-opacity-25 text-muted" data-step-indicator="2">2</span>
          <span class="text-muted small">Psicólogo</span>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="wizard-step active" data-step="1">
        <label class="form-label text-uppercase small text-muted">Buscar beneficiario</label>
        <div class="input-group input-group-sm mb-3">
          <span class="input-group-text bg-secondary text-white"><i class="bi bi-search"></i></span>
          <input id="wizardBeneficiarioSearch" type="search" class="form-control" placeholder="CURP, folio o nombre">
        </div>
        <div id="wizardSearchHint" class="alert alert-secondary py-2 small">Escribe al menos 3 caracteres para iniciar la búsqueda.</div>
        <div class="list-group" id="wizardBeneficiarioResults"></div>
      </div>
      <div class="wizard-step" data-step="2">
        <div class="mb-3">
          <label class="form-label text-uppercase small text-muted">Beneficiario seleccionado</label>
          <div id="wizardBeneficiarioSummary" class="p-3 border rounded-3 bg-body-tertiary">Ningún beneficiario seleccionado.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Selecciona psicólogo disponible</label>
          <select id="wizardPsychologistSelect" class="form-select">
            <option value="">Selecciona…</option>
          </select>
          <div class="form-text">Cada opción muestra la carga actual y un indicador visual (🟢 ligera, 🟡 media, 🟠 alta, 🔴 crítica).</div>
        </div>
        <div id="wizardAssignError" class="alert alert-warning small d-none"></div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <button type="button" class="btn btn-outline-light" data-wizard-prev>Anterior</button>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" data-wizard-next>Siguiente</button>
        <button type="button" class="btn btn-primary d-none" data-wizard-submit><i class="bi bi-check-circle me-1"></i>Asignar</button>
      </div>
    </div>
  </div>

  @once
      <style>
          #assignmentWizard .wizard-step { display: none; }
          #assignmentWizard .wizard-step.active { display: block; }
          #assignmentWizard .wizard-indicator .badge { transition: all .2s ease-in-out; }
          #assignmentWizard .wizard-indicator .badge.active { background: var(--bs-primary); color: #fff; }
      </style>
  @endonce

  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-6">
          <label class="form-label small text-uppercase text-muted">Filtrar listado</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-secondary text-white"><i class="bi bi-filter"></i></span>
            <input id="beneficiarioSearch" type="search" class="form-control" placeholder="Buscar en asignaciones">
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
      <div id="assignmentEmpty" class="text-center text-muted py-4 d-none">
        No hay coincidencias con los filtros seleccionados.
      </div>
      <div id="rows" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3"></div>
      <div id="pager" class="small text-muted mt-3"></div>
    </div>
  </div>
</div>

<!-- Modal cambiar psicólogo -->
<div class="modal fade" id="modalReassign" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Cambiar psicólogo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="m-ben">
        <input type="hidden" id="m-current">
        <label class="form-label">Nuevo psicólogo</label>
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
const workloadSignal = (value) => {
    const total = Number(value) || 0;
    if (total >= 12) return { symbol: '🔴', label: 'Carga crítica' };
    if (total >= 8) return { symbol: '🟠', label: 'Carga alta' };
    if (total >= 4) return { symbol: '🟡', label: 'Carga media' };
    return { symbol: '🟢', label: 'Carga ligera' };
};

let reloadAssignments = () => {};

async function loadPsicologosOptions(current = '') {
    const sel = document.getElementById('m-psi');
    if (!sel) return;
    sel.innerHTML = '<option value="">Cargando…</option>';
    try {
        const res = await fetch('/s360/enc360/psicologos/list');
        const data = await res.json();
        const options = (data.data || []).map(r => {
            const count = Number(r.cargas ?? 0);
            const indicator = workloadSignal(count);
            const carga = Number.isFinite(count) ? ` · ${count} pacientes` : '';
            const label = `${indicator.symbol} ${r.name} (${r.email})${carga}`;
            const title = `${indicator.label}${carga ? ` (${count} pacientes)` : ''}`;
            return `<option value="${r.id}" ${String(current) === String(r.id) ? 'selected' : ''} data-workload="${count}" title="${title}">${label}</option>`;
        }).join('');
        sel.innerHTML = '<option value="">Seleccione…</option>' + options;
    } catch (error) {
        sel.innerHTML = '<option value="">Error al cargar psicólogos</option>';
    }
}

function initAssignmentWizard() {
    const wizard = document.getElementById('assignmentWizard');
    if (!wizard) return;

    const steps = Array.from(wizard.querySelectorAll('.wizard-step'));
    const indicators = Array.from(wizard.querySelectorAll('[data-step-indicator]'));
    const prevBtn = wizard.querySelector('[data-wizard-prev]');
    const nextBtn = wizard.querySelector('[data-wizard-next]');
    const submitBtn = wizard.querySelector('[data-wizard-submit]');
    const searchInput = wizard.querySelector('#wizardBeneficiarioSearch');
    const resultsList = wizard.querySelector('#wizardBeneficiarioResults');
    const searchHint = wizard.querySelector('#wizardSearchHint');
    const summaryBox = wizard.querySelector('#wizardBeneficiarioSummary');
    const select = wizard.querySelector('#wizardPsychologistSelect');
    const errorBox = wizard.querySelector('#wizardAssignError');
    const startButtons = document.querySelectorAll('[data-wizard-start]');
    const assignUrl = wizard.dataset.assignUrl;
    const searchUrl = wizard.dataset.beneficiarioSearchUrl;
    const psicologosUrl = wizard.dataset.psicologosUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    let currentStep = 0;
    let selectedBeneficiario = null;
    let debounceTimer = null;
    let psicologosLoaded = false;
    let isLoadingPsychologists = false;

    const toggleStep = (step = currentStep) => {
        currentStep = Math.max(0, Math.min(step, steps.length - 1));
        steps.forEach((el, index) => el.classList.toggle('active', index === currentStep));
        indicators.forEach((badge, index) => {
            badge.classList.toggle('active', index === currentStep);
            if (index === currentStep) {
                badge.classList.remove('bg-secondary', 'bg-opacity-25', 'text-muted');
                badge.classList.add('bg-primary', 'text-white');
            } else {
                badge.classList.remove('bg-primary', 'text-white');
                badge.classList.add('bg-secondary', 'bg-opacity-25', 'text-muted');
            }
        });
        if (prevBtn) prevBtn.disabled = currentStep === 0;
        if (nextBtn) {
            nextBtn.classList.toggle('d-none', currentStep === steps.length - 1);
            nextBtn.disabled = currentStep === 0 && !selectedBeneficiario;
        }
        if (submitBtn) {
            submitBtn.classList.toggle('d-none', currentStep !== steps.length - 1);
            submitBtn.disabled = currentStep !== steps.length - 1 || !select?.value || !selectedBeneficiario;
        }
        if (errorBox) errorBox.classList.add('d-none');
        if (currentStep === 1 && !psicologosLoaded && !isLoadingPsychologists) {
            loadWizardPsychologists();
        }
    };

    const setSelectedBeneficiario = (item) => {
        selectedBeneficiario = item;
        if (summaryBox) {
            if (!item) {
                summaryBox.textContent = 'Ningún beneficiario seleccionado.';
            } else {
                summaryBox.innerHTML = `<div class="fw-semibold">${item.nombre}</div>
                    <div class="small text-muted">${item.curp || ''}${item.curp && item.folio ? ' · ' : ''}${item.folio || ''}</div>
                    <div class="small text-muted">${item.municipio || ''}</div>`;
            }
        }
        if (!item && resultsList) {
            resultsList.querySelectorAll('.active').forEach(el => el.classList.remove('active'));
        }
        if (nextBtn) nextBtn.disabled = !selectedBeneficiario;
        if (submitBtn) submitBtn.disabled = !select?.value || !selectedBeneficiario;
    };
    startButtons.forEach(button => {
        button.addEventListener('click', () => {
            setSelectedBeneficiario(null);
            toggleStep(0);
            wizard?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            searchInput?.focus();
        });
    });

    const renderResults = (items) => {
        if (!resultsList) return;
        if (!items.length) {
            resultsList.innerHTML = '<div class="list-group-item text-muted small">Sin resultados. Intenta con otro término.</div>';
            return;
        }
        resultsList.innerHTML = items.map(item => {
            const detalle = [item.curp, item.folio].filter(Boolean).join(' · ');
            const municipio = item.municipio ? `<span class="small text-muted">${item.municipio}</span>` : '';
            return `<button type="button" class="list-group-item list-group-item-action" data-beneficiario-id="${item.id}"
                        data-beneficiario-nombre="${item.nombre}" data-beneficiario-curp="${item.curp || ''}" data-beneficiario-folio="${item.folio || ''}" data-beneficiario-municipio="${item.municipio || ''}">
                        <div class="fw-semibold">${item.nombre}</div>
                        <div class="small text-muted">${detalle || 'Sin identificadores'}</div>
                        ${municipio}
                    </button>`;
        }).join('');
    };

    const searchBeneficiarios = async (term) => {
        if (!resultsList) return;
        resultsList.innerHTML = '<div class="list-group-item small">Cargando…</div>';
        try {
            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('q', term);
            url.searchParams.set('limit', '12');
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('request failed');
            const data = await res.json();
            const items = (data.items || data.data || []).map(row => ({
                id: row.id,
                nombre: row.nombre || row.full_name || '',
                curp: row.curp || '',
                folio: row.folio_tarjeta || row.folio || '',
                municipio: row.municipio || '',
            }));
            renderResults(items);
        } catch (error) {
            resultsList.innerHTML = '<div class="list-group-item text-warning small">No se pudo completar la búsqueda.</div>';
        }
    };

    const debouncedSearch = (term) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => searchBeneficiarios(term), 350);
    };

    const loadWizardPsychologists = async () => {
        if (!select) return;
        isLoadingPsychologists = true;
        select.innerHTML = '<option value="">Cargando…</option>';
        try {
            const res = await fetch(psicologosUrl, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            const items = data.data || data.items || [];
            select.innerHTML = '<option value="">Selecciona…</option>' + items.map(item => {
                const count = Number(item.cargas ?? 0);
                const indicator = workloadSignal(count);
                const carga = Number.isFinite(count) ? ` · ${count} pacientes` : '';
                const title = `${indicator.label}${carga ? ` (${count} pacientes)` : ''}`;
                return `<option value="${item.id}" data-workload="${count}" title="${title}">${indicator.symbol} ${item.name || 'Psicólogo'}${carga}</option>`;
            }).join('');
            psicologosLoaded = true;
        } catch (error) {
            select.innerHTML = '<option value="">Error al cargar psicólogos</option>';
        }
        isLoadingPsychologists = false;
        if (submitBtn) submitBtn.disabled = !select.value || !selectedBeneficiario;
    };

    searchInput?.addEventListener('input', (event) => {
        const term = (event.target.value || '').trim();
        setSelectedBeneficiario(null);
        if (currentStep !== 0) toggleStep(0);
        if (term.length < 3) {
            if (resultsList) resultsList.innerHTML = '';
            if (searchHint) searchHint.classList.remove('d-none');
            return;
        }
        if (searchHint) searchHint.classList.add('d-none');
        debouncedSearch(term);
    });

    resultsList?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-beneficiario-id]');
        if (!button) return;
        resultsList.querySelectorAll('.active').forEach(el => el.classList.remove('active'));
        button.classList.add('active');
        setSelectedBeneficiario({
            id: button.dataset.beneficiarioId,
            nombre: button.dataset.beneficiarioNombre,
            curp: button.dataset.beneficiarioCurp,
            folio: button.dataset.beneficiarioFolio,
            municipio: button.dataset.beneficiarioMunicipio,
        });
    });

    prevBtn?.addEventListener('click', () => {
        toggleStep(currentStep - 1);
    });

    nextBtn?.addEventListener('click', () => {
        if (!selectedBeneficiario) return;
        toggleStep(currentStep + 1);
    });

    select?.addEventListener('change', () => {
        if (submitBtn) submitBtn.disabled = !select.value || !selectedBeneficiario;
        if (errorBox) errorBox.classList.add('d-none');
    });

    submitBtn?.addEventListener('click', async () => {
        if (!selectedBeneficiario || !select?.value) {
            if (errorBox) {
                errorBox.textContent = 'Selecciona un beneficiario y un psicólogo.';
                errorBox.classList.remove('d-none');
            }
            return;
        }
        if (errorBox) errorBox.classList.add('d-none');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Asignando…';
        try {
            const res = await fetch(assignUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    beneficiario_id: selectedBeneficiario.id,
                    psicologo_id: select.value,
                }),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({ message: 'No se pudo completar la asignación.' }));
                if (errorBox) {
                    errorBox.textContent = data.message || 'No se pudo completar la asignación.';
                    errorBox.classList.remove('d-none');
                }
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Asignar';
                submitBtn.disabled = false;
                return;
            }
            window.showToast?.('Asignación creada correctamente', 'success');
            if (searchInput) searchInput.value = '';
            if (resultsList) resultsList.innerHTML = '';
            if (searchHint) searchHint.classList.remove('d-none');
            select.value = '';
            psicologosLoaded = false;
            setSelectedBeneficiario(null);
            toggleStep(0);
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Asignar';
            submitBtn.disabled = true;
            reloadAssignments?.();
        } catch (error) {
            if (errorBox) {
                errorBox.textContent = 'Ocurrió un error al guardar la asignación.';
                errorBox.classList.remove('d-none');
            }
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Asignar';
            submitBtn.disabled = false;
        }
    });

    toggleStep(0);
}

document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('rows');
    const emptyState = document.getElementById('assignmentEmpty');
    const pager = document.getElementById('pager');
    const searchInput = document.getElementById('beneficiarioSearch');
    const statusSelect = document.getElementById('f-status');
    const municipioInput = document.getElementById('f-mun');
    const chipContainer = document.getElementById('psicologoChips');
    const refreshBtn = document.getElementById('refreshPsicologos');
    const refreshAssignmentsBtn = document.getElementById('refreshAssignments');
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
        const psicologo = row.psicologo_name || 'Sin asignar';
        const fecha = (row.assigned_at || '').toString().substring(0, 10);
        const municipio = row.municipio_id ?? '—';
        return `<div class="col">
          <div class="card bg-dark border border-white text-white h-100 shadow-sm">
            <div class="card-body d-flex flex-column gap-3">
              <div>
                <h3 class="h6 text-white mb-1">${nombre}</h3>
                <div class="small text-white-50"><i class="bi bi-person-video3 me-1"></i>${psicologo}</div>
              </div>
              <div class="d-flex flex-column gap-2 small">
                <div class="d-flex justify-content-between align-items-center text-white-50">
                  <span><i class="bi bi-geo-alt me-1"></i>Municipio</span>
                  <span class="text-white fw-semibold">${municipio}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center text-white-50">
                  <span><i class="bi bi-calendar-event me-1"></i>Asignada</span>
                  <span class="text-white">${fecha || '—'}</span>
                </div>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-white-50 small text-uppercase">Estado</span>
                ${estado}
              </div>
              <div class="mt-auto">
                <button class="btn btn-outline-light btn-sm w-100" data-action="reassign" data-ben="${row.beneficiario_id}" data-currentpsi="${row.psicologo_id || ''}">
                  <i class="bi bi-arrow-repeat me-1"></i>Cambiar psicólogo
                </button>
              </div>
            </div>
          </div>
        </div>`;
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
        if (!grid) return;
        const query = normalize(searchInput ? searchInput.value : '');
        const filtered = currentRows.filter(row => {
            const fullName = normalize(`${row.ben_nombre || ''} ${row.ben_apellido_paterno || ''} ${row.ben_apellido_materno || ''}`);
            const psychologist = normalize(row.psicologo_name || '');
            return !query || fullName.includes(query) || psychologist.includes(query);
        });
        if (!filtered.length) {
            grid.innerHTML = '';
            grid.classList.add('d-none');
            emptyState?.classList.remove('d-none');
        } else {
            grid.innerHTML = filtered.map(buildRowHtml).join('');
            grid.classList.remove('d-none');
            emptyState?.classList.add('d-none');
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
            allBtn.title = 'Mostrar todos los psicólogos';
            fragment.appendChild(allBtn);
            items.forEach(item => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.psi = item.id;
                btn.className = `btn btn-sm btn-outline-secondary ${String(item.id) === String(selectedPsychologist) ? 'active' : ''}`;
                const count = Number(item.cargas ?? 0);
                const indicator = workloadSignal(count);
                const carga = Number.isFinite(count) ? ` · ${count} pacientes` : '';
                btn.textContent = `${indicator.symbol} ${item.name || 'Psicólogo'}${carga}`;
                btn.title = `${indicator.label}${carga ? ` (${count} pacientes)` : ''}`;
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

    refreshBtn?.addEventListener('click', () => renderPsychologistChips());
    refreshAssignmentsBtn?.addEventListener('click', () => fetchAssignments(pagination.current_page ?? 1));
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
    initAssignmentWizard();
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



