<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">
            {{ auth()->user()->hasRole('psicologo') ? __('Mis pacientes') : __('Dashboard') }}
        </h2>
    </x-slot>

    @role('psicologo')
    <div class="card">
        <div class="card-body" id="agenda-container">
            <div class="text-muted">Cargando…</div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const res = await fetch('/s360/psico/agenda-semana');
            const data = await res.json();
            const container = document.getElementById('agenda-container');
            const items = data.items || [];
            if (!items.length) {
                container.innerHTML = '<div class="text-muted">No hay citas para esta semana.</div>';
                return;
            }
            const rows = items.map(item => {
                const badgeClass = item.estado === 'atendido' ? 'bg-success' : 'bg-warning text-dark';
                const estadoText = item.estado === 'atendido' ? 'Atendido' : 'Pendiente';
                return `<li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>${item.nombre}</span>
                    <div>
                        <span class="badge ${badgeClass} me-2">${estadoText}</span>
                        <span class="text-muted">${item.fecha}</span>
                    </div>
                </li>`;
            }).join('');
            container.innerHTML = `<ul class="list-group">${rows}</ul>`;
        } catch (e) {
            document.getElementById('agenda-container').innerHTML = '<div class="text-danger">Error al cargar la agenda.</div>';
        }
    });
    </script>
    @else
    <div class="card">
        <div class="card-body">
            {{ __("You're logged in!") }}
        </div>
    </div>
    @endrole
</x-app-layout>
