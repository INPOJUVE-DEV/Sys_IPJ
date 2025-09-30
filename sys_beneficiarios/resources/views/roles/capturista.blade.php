<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Mi progreso</h2></x-slot>

    <div data-kpis-url="{{ route('capturista.kpis') }}">
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Hoy</div><div class="h3" id="kpiToday">—</div></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Semana</div><div class="h3" id="kpiWeek">—</div></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Últimos 30 días</div><div class="h3" id="kpi30">—</div></div></div></div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6"><div class="card"><div class="card-header">Mis altas (30 días)</div><div class="card-body"><canvas id="chartMine" height="200"></canvas></div></div></div>
            <div class="col-lg-6"><div class="card"><div class="card-header">Últimos registros</div><div class="card-body"><ul class="list-group" id="ultimosList"></ul></div></div></div>
        </div>
    </div>

    @vite(['resources/js/dashboard.js'])
</x-app-layout>
