<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1 class="h4 m-0">Dashboard Voluntariado</h1>
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#createGroupCard" aria-expanded="false" aria-controls="createGroupCard">
                Crear grupo
            </button>
        </div>
    </x-slot>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="createGroupCard" class="collapse mb-4 {{ $errors->any() ? 'show' : '' }}">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('vol.groups.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Sede</label>
                        <select name="site_id" class="form-select" required>
                            <option value="">Seleccione</option>
                            @foreach($sites as $id => $name)
                                <option value="{{ $id }}" @selected(old('site_id') == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nombre del grupo</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo</label>
                        <select name="type" class="form-select" required>
                            @foreach(['semanal' => 'Semanal', 'sabatino' => 'Sabatino'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Plantilla</label>
                        <select name="schedule_template" class="form-select" required>
                            @foreach(['lmv' => 'Lunes a Miercoles', 'mj' => 'Jueves y Viernes', 'sab' => 'Sabado'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('schedule_template') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de inicio</label>
                        <input type="date" name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Capacidad</label>
                        <input type="number" name="capacity" min="0" value="{{ old('capacity', 12) }}" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">El programa se asigna automaticamente a Jóvenes al Volante y la fecha de cierre se ajusta al ultimo día del mes.</small>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-success">Guardar grupo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Mes de referencia</label>
                    <input type="month" name="month" value="{{ $filters['month'] ?? now()->format('Y-m') }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ano</label>
                    <input type="number" name="year" value="{{ $filters['year'] ?? now()->year }}" class="form-control" min="2020" max="2100">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Trimestre</label>
                    <select name="quarter" class="form-select">
                        @foreach([1,2,3,4] as $q)
                            <option value="{{ $q }}" @selected(($filters['quarter'] ?? '') == $q)>Q{{ $q }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sede</label>
                    <select name="site_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($sites as $id => $name)
                            <option value="{{ $id }}" @selected(($filters['site_id'] ?? '') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 text-end">
                    <button class="btn btn-outline-light w-100">Aplicar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">Inscripciones totales (trimestre)</div>
                <div class="card-body">
                    <canvas id="chartTotals" height="200" aria-label="Inscripciones totales" role="img"></canvas>
                    <div class="text-muted small mt-2" id="chartTotalsEmpty" style="display:none;">Sin datos disponibles para el periodo seleccionado.</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">Inscritos por sede (mes)</div>
                <div class="card-body">
                    <canvas id="chartSites" height="200" aria-label="Inscripciones por sede" role="img"></canvas>
                    <div class="text-muted small mt-2" id="chartSitesEmpty" style="display:none;">No se registran inscripciones en el mes.</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">Estado de pagos</div>
                <div class="card-body">
                    <canvas id="chartPayments" height="200" aria-label="Estado de pagos" role="img"></canvas>
                    <div class="text-muted small mt-2" id="chartPaymentsEmpty" style="display:none;">Aún no hay pagos registrados en el periodo.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-bg-dark h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Inscripciones del mes</div>
                    <div class="display-6 fw-bold">{{ $monthly['total'] ?? 0 }}</div>
                    <div class="text-muted small">Periodo {{ $monthly['period'] ?? '' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-bg-dark h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Total trimestre</div>
                    <div class="display-6 fw-bold">{{ $quarterly['total'] ?? 0 }}</div>
                    <div class="text-muted small">Q{{ $quarterly['quarter'] ?? '' }} {{ $quarterly['year'] ?? '' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-bg-dark h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Disponibilidad actual</div>
                    <div class="display-6 fw-bold">{{ $availability['totals']['available'] ?? 0 }}</div>
                    <div class="text-muted small">Capacidad {{ $availability['totals']['capacity'] ?? 0 }} | Inscritos {{ $availability['totals']['active'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-12">
            <div class="card h-100">
                <div class="card-header">Resumen mensual por sede</div>
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                        @forelse($monthly['per_site'] ?? [] as $site)
                            <div class="col">
                                <div class="card bg-dark border border-white text-white h-100 shadow-sm">
                                    <div class="card-body d-flex flex-column gap-3">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <h3 class="h6 text-white mb-1">{{ $site['site_name'] ?? 'N/D' }}</h3>
                                                <div class="small text-white-50">Resumen mensual</div>
                                            </div>
                                            <span class="badge bg-primary bg-opacity-75 text-white">{{ $site['total'] ?? 0 }} totales</span>
                                        </div>
                                        <div class="d-flex flex-column gap-2 small">
                                            <div class="d-flex justify-content-between text-white-50">
                                                <span><i class="bi bi-gender-male me-1"></i>Hombres</span>
                                                <span class="text-white fw-semibold">{{ $site['male'] ?? 0 }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between text-white-50">
                                                <span><i class="bi bi-gender-female me-1"></i>Mujeres</span>
                                                <span class="text-white fw-semibold">{{ $site['female'] ?? 0 }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between text-white-50">
                                                <span><i class="bi bi-people me-1"></i>Menores</span>
                                                <span class="text-white fw-semibold">{{ $site['minors'] ?? 0 }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between text-white-50">
                                                <span><i class="bi bi-person-vcard me-1"></i>Mayores</span>
                                                <span class="text-white fw-semibold">{{ $site['adults'] ?? 0 }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-center text-muted py-3">Sin datos para el mes.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Resumen trimestral</div>
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        @forelse($quarterly['per_month'] ?? [] as $row)
                            <div class="col">
                                <div class="card bg-dark border border-white text-white h-100 shadow-sm">
                                    <div class="card-body d-flex flex-column gap-2">
                                        <span class="text-white-50 small text-uppercase">Periodo</span>
                                        <h3 class="h5 text-white mb-1">{{ $row['period'] ?? 'N/D' }}</h3>
                                        <div class="display-6 fw-bold">{{ $row['total'] ?? 0 }}</div>
                                        <div class="text-white-50 small">Inscripciones registradas</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-center text-muted py-3">Sin datos para el trimestre.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Trimestre por sede</div>
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        @forelse($quarterly['per_site'] ?? [] as $site)
                            <div class="col">
                                <div class="card bg-dark border border-white text-white h-100 shadow-sm">
                                    <div class="card-body d-flex flex-column gap-2">
                                        <h3 class="h6 text-white mb-1">{{ $site['site_name'] ?? 'N/D' }}</h3>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-white-50 small text-uppercase">Inscritos</span>
                                            <span class="badge bg-secondary text-white">{{ $site['total'] ?? 0 }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-center text-muted py-3">Sin datos para el trimestre.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Disponibilidad de grupos</div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @forelse($availability['groups'] ?? [] as $group)
                    <div class="col">
                        <div class="card bg-dark border border-white text-white h-100 shadow-sm">
                            <div class="card-body d-flex flex-column gap-3">
                                <div>
                                    <h3 class="h6 text-white mb-1">{{ $group['group_name'] ?? 'N/D' }}</h3>
                                    <div class="small text-white-50">{{ $group['code'] ?? '' }}</div>
                                    <div class="small text-white-50"><i class="bi bi-geo-alt me-1"></i>{{ $group['site_name'] ?? 'N/D' }}</div>
                                </div>
                                <div class="d-flex flex-column gap-2 small">
                                    <div class="d-flex justify-content-between text-white-50">
                                        <span><i class="bi bi-people-fill me-1"></i>Capacidad</span>
                                        <span class="text-white fw-semibold">{{ $group['capacity'] ?? 0 }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between text-white-50">
                                        <span><i class="bi bi-person-check me-1"></i>Inscritos</span>
                                        <span class="text-white fw-semibold">{{ $group['active'] ?? 0 }}</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-white-50 small text-uppercase">Disponibles</span>
                                    <span class="badge bg-success">{{ $group['available'] ?? 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center text-muted py-3">Sin grupos con cupo disponible.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="alert alert-secondary">
        <strong>Notas operativas:</strong> {{ $copy['message'] ?? '' }}
    </div>
</x-app-layout>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js" integrity="sha384-Mww7VW2PHy1/Ml5fpvcxAWWNjPJ6sWz5PaJjzTHnGp0VjcHFLMLpXZYmNbPliF6O" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const totalsCanvas = document.getElementById('chartTotals');
            const totalsEmpty = document.getElementById('chartTotalsEmpty');
            const totalsRaw = @json($quarterly['per_month'] ?? []);
            const totalsData = Array.isArray(totalsRaw) ? totalsRaw : [];

            if (totalsCanvas && totalsData.length > 0) {
                if (totalsEmpty) totalsEmpty.style.display = 'none';
                new Chart(totalsCanvas, {
                    type: 'bar',
                    data: {
                        labels: totalsData.map(row => row.period ?? ''),
                        datasets: [{
                            label: 'Inscripciones',
                            data: totalsData.map(row => row.total ?? 0),
                            backgroundColor: '#38bdf8',
                        }],
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                        },
                        scales: {
                            y: {
                                ticks: { precision: 0 },
                                beginAtZero: true,
                            },
                        },
                    },
                });
            } else if (totalsCanvas) {
                totalsCanvas.style.display = 'none';
            }

            const sitesCanvas = document.getElementById('chartSites');
            const sitesEmpty = document.getElementById('chartSitesEmpty');
            const sitesRaw = @json($monthly['per_site'] ?? []);
            const sitesData = Array.isArray(sitesRaw) ? sitesRaw : [];

            if (sitesCanvas && sitesData.length > 0) {
                if (sitesEmpty) sitesEmpty.style.display = 'none';
                new Chart(sitesCanvas, {
                    type: 'bar',
                    data: {
                        labels: sitesData.map(row => row.site_name ?? 'N/D'),
                        datasets: [{
                            label: 'Inscritos',
                            data: sitesData.map(row => row.total ?? 0),
                            backgroundColor: '#c084fc',
                        }],
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                        },
                        scales: {
                            y: {
                                ticks: { precision: 0 },
                                beginAtZero: true,
                            },
                        },
                    },
                });
            } else if (sitesCanvas) {
                sitesCanvas.style.display = 'none';
            }

            const paymentCanvas = document.getElementById('chartPayments');
            const paymentEmpty = document.getElementById('chartPaymentsEmpty');
            const paymentStats = @json($payments ?? []);
            const paymentValues = [paymentStats.paid ?? 0, paymentStats.pending ?? 0];
            const paymentTotal = paymentValues.reduce((sum, value) => sum + (Number(value) || 0), 0);

            if (paymentCanvas && paymentTotal > 0) {
                if (paymentEmpty) paymentEmpty.style.display = 'none';
                new Chart(paymentCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pagado', 'Pendiente'],
                        datasets: [{
                            data: paymentValues,
                            backgroundColor: ['#34d399', '#fbbf24'],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                },
                            },
                        },
                    },
                });
            } else if (paymentCanvas) {
                paymentCanvas.style.display = 'none';
            }
        });
    </script>
@endpush
