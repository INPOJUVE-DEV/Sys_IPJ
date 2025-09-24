<x-app-layout>
<div class="container py-4">
  <h1 class="h4 mb-3">Mis pacientes asignados</h1>

  <div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <span class="fw-semibold">Agenda semanal</span>
        <div class="text-muted small">Haz clic en una cita para registrar la sesión.</div>
      </div>
      <button class="btn btn-outline-light btn-sm" type="button" id="refreshAgenda"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar</button>
    </div>
    <div class="card-body">
      <div class="row row-cols-1 row-cols-md-7 g-2" id="calendarGrid"></div>
      <div class="text-muted small mt-3" id="calendarEmpty" style="display:none;">No hay citas programadas esta semana.</div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="row g-2 mb-3">
        <div class="col-12 col-md-6">
          <input type="text" id="search" class="form-control" placeholder="Buscar beneficiario…">
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle" id="tabla">
          <thead class="table-light">
            <tr>
              <th>Nombre</th>
              <th>Teléfono</th>
              <th>Seccional</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.querySelector('#tabla tbody');
  const searchInput = document.getElementById('search');
  const calendarGrid = document.getElementById('calendarGrid');
  const calendarEmpty = document.getElementById('calendarEmpty');
  const refreshAgendaBtn = document.getElementById('refreshAgenda');
  let patients = [];

  const renderTable = () => {
    if (!tbody) return;
    const query = (searchInput?.value || '').toLowerCase();
    const filtered = patients.filter(r => {
      const name = `${r.nombre || ''} ${r.apellido_paterno || ''} ${r.apellido_materno || ''}`.toLowerCase();
      return !query || name.includes(query);
    });
    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Sin pacientes asignados.</td></tr>';
      return;
    }
    tbody.innerHTML = filtered.map(r => {
      const nombre = `${r.nombre} ${r.apellido_paterno} ${r.apellido_materno}`;
      const telefono = r.telefono || '';
      const seccional = r.seccional || '';
      return `<tr>
        <td>${nombre}</td>
        <td>${telefono}</td>
        <td>${seccional}</td>
        <td>
          <a class="btn btn-sm btn-primary" href="/s360/psico/paciente/${r.id}/show">Abrir</a>
          <a class="btn btn-sm btn-outline-secondary" href="/s360/psico/sesiones/${r.id}/show">Historial</a>
        </td>
      </tr>`;
    }).join('');
  };

  const startOfWeek = () => {
    const today = new Date();
    const day = today.getDay();
    const diff = day === 0 ? -6 : 1 - day; // lunes como inicio
    const monday = new Date(today);
    monday.setDate(today.getDate() + diff);
    monday.setHours(0, 0, 0, 0);
    return monday;
  };

  const renderCalendar = (items = []) => {
    if (!calendarGrid) return;
    const eventsByDate = items.reduce((acc, item) => {
      const key = (item.fecha || '').substring(0, 10);
      if (!acc[key]) acc[key] = [];
      acc[key].push(item);
      return acc;
    }, {});
    const names = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    const base = startOfWeek();
    const todayIso = new Date().toISOString().slice(0, 10);
    const cols = [];
    for (let i = 0; i < 7; i++) {
      const date = new Date(base);
      date.setDate(base.getDate() + i);
      const iso = date.toISOString().slice(0, 10);
      const readable = date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
      const events = eventsByDate[iso] || [];
      const highlight = iso === todayIso ? 'border-primary border-2' : 'border-secondary';
      const eventsHtml = events.length ? events.map(event => {
        const time = new Date(event.fecha).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        const status = event.estado === 'pendiente' ? 'Pendiente' : 'Atendida';
        const statusClass = event.estado === 'pendiente' ? 'btn-outline-warning' : 'btn-outline-success';
        return `<button type="button" class="btn btn-sm ${statusClass} w-100 text-start calendar-event mb-2" data-beneficiario="${event.beneficiario_id}" data-fecha="${event.fecha}">
          <div class="fw-semibold">${time}</div>
          <div>${event.nombre}</div>
          <div class="small text-muted">${status}</div>
        </button>`;
      }).join('') : '<div class="text-muted small">Sin citas</div>';
      cols.push(`<div class="col"><div class="calendar-day bg-body-tertiary ${highlight} rounded-3 p-2 h-100">
        <div class="fw-semibold">${names[i]}</div>
        <div class="text-muted small mb-2">${readable}</div>
        ${eventsHtml}
      </div></div>`);
    }
    calendarGrid.innerHTML = cols.join('');
    if (calendarEmpty) {
      calendarEmpty.style.display = items.length ? 'none' : 'block';
    }
  };

  const loadPatients = async () => {
    try {
      const res = await fetch('/s360/psico/pacientes');
      if (!res.ok) throw new Error('Error');
      const data = await res.json();
      patients = data.items || [];
      renderTable();
    } catch (error) {
      patients = [];
      renderTable();
    }
  };

  const loadAgenda = async () => {
    try {
      const res = await fetch('/s360/psico/agenda-semana');
      if (!res.ok) throw new Error('Error');
      const data = await res.json();
      renderCalendar(data.items || []);
    } catch (error) {
      renderCalendar([]);
      if (calendarEmpty) {
        calendarEmpty.textContent = 'No fue posible cargar la agenda.';
        calendarEmpty.style.display = 'block';
      }
    }
  };

  calendarGrid?.addEventListener('click', (event) => {
    const target = event.target.closest('[data-beneficiario]');
    if (!target) return;
    const beneficiario = target.getAttribute('data-beneficiario');
    if (beneficiario) {
      window.location.href = `/s360/psico/paciente/${beneficiario}/show`;
    }
  });

  refreshAgendaBtn?.addEventListener('click', () => loadAgenda());
  searchInput?.addEventListener('input', renderTable);

  loadPatients();
  loadAgenda();
});
</script>
</x-app-layout>
