<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="h4 m-0">{{ $group->name }} <small class="text-muted">({{ $group->code }})</small></h1>
                <div class="text-muted small">Sede: {{ $group->site->name ?? 'N/D' }} &bull; Programa: {{ $group->program->name ?? 'N/D' }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('update', $group)
                    <a href="{{ route('vol.groups.edit', $group) }}" class="btn btn-outline-light">Editar</a>
                @endcan
                @can('create', App\Models\VolEnrollment::class)
                    <a href="{{ route('vol.enrollments.create', $group) }}" class="btn btn-success">Asignar beneficiario</a>
                @endcan
                @can('create', App\Models\VolPayment::class)
                    <a href="{{ route('vol.payments.create') }}" class="btn btn-outline-light">Registrar pago</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-bg-dark h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Estado</div>
                    <div class="fw-bold text-capitalize">{{ $group->state }}</div>
                    <div class="mt-2">
                        @can('update', $group)
                            <div class="d-flex gap-2 flex-wrap">
                                @if($group->state !== 'publicado')
                                    <form action="{{ route('vol.groups.publish', $group) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">Publicar</button>
                                    </form>
                                @endif
                                @if($group->state !== 'cerrado')
                                    <form action="{{ route('vol.groups.close', $group) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Cerrar</button>
                                    </form>
                                @endif
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-dark h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Capacidad</div>
                    <div class="display-6 fw-bold">{{ $group->capacity ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-dark h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Inscritos</div>
                    <div class="display-6 fw-bold">{{ $activeCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-dark h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Disponibles</div>
                    <div class="display-6 fw-bold">{{ max(0, ($group->capacity ?? 0) - $activeCount) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body row g-3 small">
            <div class="col-md-3">
                <span class="text-muted">Tipo</span>
                <div class="fw-semibold">{{ $group->type }}</div>
            </div>
            <div class="col-md-3">
                <span class="text-muted">Plantilla</span>
                <div class="fw-semibold text-uppercase">{{ $group->schedule_template }}</div>
            </div>
            <div class="col-md-3">
                <span class="text-muted">Inicio</span>
                <div class="fw-semibold">{{ optional($group->start_date)->format('Y-m-d') ?? 'N/D' }}</div>
            </div>
            <div class="col-md-3">
                <span class="text-muted">Cierre</span>
                <div class="fw-semibold">{{ optional($group->end_date)->format('Y-m-d') ?? 'N/D' }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold">Inscripciones</span>
            <span class="text-muted small">Mostrando {{ $enrollments->firstItem() ?? 0 }}-{{ $enrollments->lastItem() ?? 0 }} de {{ $enrollments->total() }}</span>
        </div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @forelse($enrollments as $enrollment)
                    <div class="col">
                        <div class="card bg-dark border border-white text-white h-100 shadow-sm">
                            <div class="card-body d-flex flex-column gap-3">
                                <div>
                                    <h3 class="h6 text-white mb-1">{{ $enrollment->beneficiario->nombre ?? 'N/D' }} {{ $enrollment->beneficiario->apellido_paterno ?? '' }} {{ $enrollment->beneficiario->apellido_materno ?? '' }}</h3>
                                    <div class="small text-white-50"><i class="bi bi-person-vcard me-1"></i>{{ $enrollment->beneficiario->curp ?? 'N/D' }}</div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-white-50 small text-uppercase">Estado</span>
                                    @if($enrollment->status === 'inscrito')
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-secondary text-uppercase">{{ $enrollment->status }}</span>
                                    @endif
                                </div>
                                <div class="text-white-50 small">
                                    <i class="bi bi-calendar-event me-1"></i>{{ optional($enrollment->enrolled_at)->format('Y-m-d H:i') ?? 'N/D' }}
                                </div>
                                @can('delete', $enrollment)
                                    @if($enrollment->status === 'inscrito')
                                        <div class="mt-auto">
                                            <form action="{{ route('vol.enrollments.destroy', $enrollment) }}" method="POST" class="m-0" onsubmit="return confirm('Dar de baja esta inscripcion?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                    <i class="bi bi-person-dash me-1"></i>Dar de baja
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center text-muted py-4">Aun no hay inscripciones registradas.</div>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="card-footer">{{ $enrollments->links() }}</div>
    </div>
</x-app-layout>
