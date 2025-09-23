<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1 class="h4 m-0">Dashboard Voluntariado</h1>
        </div>
    </x-slot>

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
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Resumen mensual por sede</div>
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
                                @forelse($monthly['per_site'] ?? [] as $site)
                                    <tr>
                                        <td>{{ $site['site_name'] ?? 'N/D' }}</td>
                                        <td class="text-end">{{ $site['total'] ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center py-3">Sin datos para el mes.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Top grupos del mes</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Grupo</th>
                                    <th>Sede</th>
                                    <th class="text-end">Inscritos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($monthly['per_group'] ?? [] as $group)
                                    <tr>
                                        <td>{{ $group['group_name'] ?? 'N/D' }}<br><span class="text-muted small">{{ $group['code'] ?? '' }}</span></td>
                                        <td>{{ $group['site_name'] ?? 'N/D' }}</td>
                                        <td class="text-end">{{ $group['total'] ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center py-3">Sin datos para el mes.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Detalle de beneficiarios (ultimos registros)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Beneficiario</th>
                            <th>Grupo</th>
                            <th>Sede</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($monthly['beneficiaries'] ?? [] as $beneficiario)
                            <tr>
                                <td>{{ $beneficiario['nombre'] ?? 'N/D' }}</td>
                                <td>{{ $beneficiario['group_name'] ?? 'N/D' }}</td>
                                <td>{{ $beneficiario['site_name'] ?? 'N/D' }}</td>
                                <td>{{ $beneficiario['enrolled_at'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-3">Aun no hay inscripciones registradas para este periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
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
                            <tr><td colspan="5" class="text-center py-3">Sin grupos registrados.</td></tr>
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