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
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Sede</th>
                                    <th class="text-end">Inscritos</th>
                                    <th class="text-end">Hombres</th>
                                    <th class="text-end">Mujeres</th>
                                    <th class="text-end">Menores</th>
                                    <th class="text-end">Mayores</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($monthly['per_site'] ?? [] as $site)
                                    <tr>
                                        <td>{{ $site['site_name'] ?? 'N/D' }}</td>
                                        <td class="text-end">{{ $site['total'] ?? 0 }}</td>
                                        <td class="text-end">{{ $site['male'] ?? 0 }}</td>
                                        <td class="text-end">{{ $site['female'] ?? 0 }}</td>
                                        <td class="text-end">{{ $site['minors'] ?? 0 }}</td>
                                        <td class="text-end">{{ $site['adults'] ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center py-3">Sin datos para el mes.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Resumen trimestral</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Periodo</th>
                                    <th class="text-end">Inscritos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($quarterly['per_month'] ?? [] as $row)
                                    <tr>
                                        <td>{{ $row['period'] ?? 'N/D' }}</td>
                                        <td class="text-end">{{ $row['total'] ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center py-3">Sin datos para el trimestre.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Trimestre por sede</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Sede</th>
                                    <th class="text-end">Inscritos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($quarterly['per_site'] ?? [] as $site)
                                    <tr>
                                        <td>{{ $site['site_name'] ?? 'N/D' }}</td>
                                        <td class="text-end">{{ $site['total'] ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center py-3">Sin datos para el trimestre.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Disponibilidad de grupos</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Grupo</th>
                            <th>Sede</th>
                            <th class="text-end">Capacidad</th>
                            <th class="text-end">Inscritos</th>
                            <th class="text-end">Disponibles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($availability['groups'] ?? [] as $group)
                            <tr>
                                <td>{{ $group['group_name'] ?? 'N/D' }}<br><span class="text-muted small">{{ $group['code'] ?? '' }}</span></td>
                                <td>{{ $group['site_name'] ?? 'N/D' }}</td>
                                <td class="text-end">{{ $group['capacity'] ?? 0 }}</td>
                                <td class="text-end">{{ $group['active'] ?? 0 }}</td>
                                <td class="text-end">{{ $group['available'] ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-3">Sin grupos con cupo disponible.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-secondary">
        <strong>Notas operativas:</strong> {{ $copy['message'] ?? '' }}
    </div>
</x-app-layout>
