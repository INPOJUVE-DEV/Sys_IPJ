<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h2 class="h4 m-0">Panel de Delegacion</h2>
                <div class="text-muted small">Indicadores operativos de eventos, inscripciones e inventario.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('eventos.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-calendar-event me-1"></i>Eventos
                </a>
                <a href="{{ route('inscripciones.list') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-calendar-check me-1"></i>Inscripciones
                </a>
                <a href="{{ route('delegacion.inventario.tarjetas.index') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-credit-card-2-front me-1"></i>Tarjetas
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h3 class="h5 fw-semibold mb-0">Indicadores de eventos</h3>
                <div class="small text-muted">Actividad registrada para esta delegacion.</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Eventos totales</div>
                        <div class="h3 mb-0">{{ number_format($eventosSummary['total']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Este mes</div>
                        <div class="h3 mb-0">{{ number_format($eventosSummary['mes']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Hoy</div>
                        <div class="h3 mb-0">{{ number_format($eventosSummary['hoy']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Asistentes</div>
                        <div class="h3 mb-0">{{ number_format($eventosSummary['asistentes']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Anfitrion / invitado</div>
                        <div class="h3 mb-0">{{ number_format($eventosSummary['anfitrion']) }} / {{ number_format($eventosSummary['invitado']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div>
                    <div class="fw-semibold">Eventos por tipo</div>
                    <div class="small text-muted">Top de tipos registrados y asistentes acumulados.</div>
                </div>
                <a href="{{ route('eventos.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Nuevo evento
                </a>
            </div>
            <div class="card-body table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th class="text-end">Eventos</th>
                            <th class="text-end">Asistentes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($eventosPorTipo as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row->tipo?->nombre ?? 'Sin tipo' }}</td>
                                <td class="text-end">{{ number_format((int) $row->total) }}</td>
                                <td class="text-end">{{ number_format((int) $row->asistentes) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted">Aun no hay eventos registrados para esta delegacion.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h3 class="h5 fw-semibold mb-0">Indicadores de inscripciones</h3>
                <div class="small text-muted">Periodo actual: {{ $periodoActual }}</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Inscripciones totales</div>
                        <div class="h3 mb-0">{{ number_format($inscripcionesSummary['total']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Periodo actual</div>
                        <div class="h3 mb-0">{{ number_format($inscripcionesSummary['periodo']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Creadas este mes</div>
                        <div class="h3 mb-0">{{ number_format($inscripcionesSummary['mes']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Activas</div>
                        <div class="h3 mb-0">{{ number_format($inscripcionesSummary['activas']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div>
                    <div class="fw-semibold">Inscripciones por programa</div>
                    <div class="small text-muted">Programas con actividad en el periodo actual.</div>
                </div>
                <a href="{{ route('inscripciones.index') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Nueva inscripcion
                </a>
            </div>
            <div class="card-body table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Programa</th>
                            <th class="text-end">Inscripciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inscripcionesPorPrograma as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row->programa?->nombre ?? 'Sin programa' }}</td>
                                <td class="text-end">{{ number_format((int) $row->total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-muted">Aun no hay inscripciones en el periodo actual.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <div>
                <h3 class="h5 fw-semibold mb-0">Inventario actual de tarjetas</h3>
                <div class="small text-muted">Resumen rapido del stock regional y su avance de captura.</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total region</div>
                        <div class="h3 mb-0">{{ number_format($tarjetasSummary['total']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Listas para asignar</div>
                        <div class="h3 mb-0">{{ number_format($tarjetasSummary['listas']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Asignadas a municipio</div>
                        <div class="h3 mb-0">{{ number_format($tarjetasSummary['municipio']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Capturadas</div>
                        <div class="h3 mb-0">{{ number_format($tarjetasSummary['capturadas']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Incidencias</div>
                        <div class="h3 mb-0">{{ number_format($tarjetasSummary['incidencias']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                <div>
                    <h4 class="h5 fw-semibold mb-0">Tarjetas capturadas de sus municipios</h4>
                    <div class="small text-muted">Capturas consumidas en municipios asignados a esta delegacion.</div>
                </div>
                <span class="badge bg-primary text-white">Edad objetivo {{ $edadObjetivoMin }}-{{ $edadObjetivoMax }}</span>
            </div>

            <div class="row g-3">
                <div class="col-sm-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">Capturadas</div>
                            <div class="h3 mb-0">{{ number_format($capturadasSummary['total']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">Capturadas este mes</div>
                            <div class="h3 mb-0">{{ number_format($capturadasSummary['mes']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">Capturadas hoy</div>
                            <div class="h3 mb-0">{{ number_format($capturadasSummary['hoy']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">En edad objetivo</div>
                            <div class="h3 mb-0">{{ number_format($capturadasSummary['edad_objetivo']) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">Fuera de rango</div>
                            <div class="h3 mb-0">{{ number_format($capturadasSummary['menores'] + $capturadasSummary['mayores']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-12 col-xl-5">
                    <div class="card shadow-sm h-100">
                        <div class="card-header">
                            <div class="fw-semibold">Tablero de edad objetivo</div>
                            <div class="small text-muted">Distribucion de beneficiarios con tarjeta capturada.</div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 text-center">
                                <div class="col-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="small text-muted">Edad objetivo</div>
                                        <div class="h4 mb-0">{{ number_format($capturadasSummary['edad_objetivo']) }}</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="small text-muted">Menores de {{ $edadObjetivoMin }}</div>
                                        <div class="h4 mb-0">{{ number_format($capturadasSummary['menores']) }}</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="small text-muted">Mayores de {{ $edadObjetivoMax }}</div>
                                        <div class="h4 mb-0">{{ number_format($capturadasSummary['mayores']) }}</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="small text-muted">Sin beneficiario</div>
                                        <div class="h4 mb-0">{{ number_format($capturadasSummary['sin_beneficiario']) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="border-top mt-3 pt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small">Mujeres en objetivo</span>
                                    <span class="fw-semibold">{{ number_format($capturadasSummary['mujeres_objetivo']) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small">Hombres en objetivo</span>
                                    <span class="fw-semibold">{{ number_format($capturadasSummary['hombres_objetivo']) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small">Otro / sin especificar</span>
                                    <span class="fw-semibold">{{ number_format($capturadasSummary['otro_objetivo']) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-7">
                    <div class="card shadow-sm h-100">
                        <div class="card-header">
                            <div class="fw-semibold">Capturadas por municipio</div>
                            <div class="small text-muted">Municipios con mayor captura y su avance en edad objetivo.</div>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Municipio</th>
                                        <th class="text-end">Tarjetas asignadas</th>
                                        <th class="text-end">Capturadas</th>
                                        <th class="text-end">Edad objetivo</th>
                                        <th class="text-end">Fuera de rango</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($capturadasPorMunicipio as $row)
                                        <tr>
                                            <td class="fw-semibold">{{ $row->municipio?->nombre ?? 'Sin municipio especifico' }}</td>
                                            <td class="text-end">{{ number_format((int) $row->asignadas) }}</td>
                                            <td class="text-end">{{ number_format((int) $row->capturadas) }}</td>
                                            <td class="text-end">{{ number_format((int) $row->edad_objetivo) }}</td>
                                            <td class="text-end">{{ number_format((int) $row->menores + (int) $row->mayores) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-muted">Aun no hay tarjetas capturadas en los municipios de esta delegacion.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
