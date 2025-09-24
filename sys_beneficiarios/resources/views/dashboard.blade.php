@php
    $user = auth()->user();
    $isAdmin = $user?->hasRole('admin');
    $isPsychologist = $user?->hasRole('psicologo');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <h2 class="h4 mb-0">
                @if($isAdmin)
                    {{ __('Centro de control') }}
                @elseif($isPsychologist)
                    {{ __('Mis pacientes y agenda') }}
                @else
                    {{ __('Dashboard') }}
                @endif
            </h2>
            @if($isAdmin)
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('beneficiarios.create') }}" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-person-plus-fill me-1"></i>{{ __('Nuevo beneficiario') }}
                    </a>
                    <a href="{{ route('s360.admin.dash') }}" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-heart-pulse me-1"></i>{{ __('Salud 360') }}
                    </a>
                    <a href="{{ route('vol.dashboard') }}" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-steering-wheel me-1"></i>{{ __('Jóvenes al Volante') }}
                    </a>
                </div>
            @elseif($isPsychologist)
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('s360.psico.view') }}" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-calendar-week me-1"></i>{{ __('Agenda completa') }}
                    </a>
                </div>
            @endif
        </div>
    </x-slot>

    @if($isAdmin)
        @php
            $now = \Carbon\Carbon::now();
            $todayStart = $now->copy()->startOfDay();
            $weekStart = $now->copy()->startOfWeek();
            $upcomingLimit = $now->copy()->addDays(7)->endOfDay();
            $monthStart = $now->copy()->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $beneficiariosAggregate = \App\Models\Beneficiario::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_draft = 0 THEN 1 ELSE 0 END) as registrados,
                SUM(CASE WHEN is_draft = 1 THEN 1 ELSE 0 END) as borradores
            ')->first();
            $beneficiariosMetrics = [
                'total' => (int) ($beneficiariosAggregate->total ?? 0),
                'registrados' => (int) ($beneficiariosAggregate->registrados ?? 0),
                'borradores' => (int) ($beneficiariosAggregate->borradores ?? 0),
                'hoy' => (int) \App\Models\Beneficiario::whereBetween('created_at', [$todayStart, $now])->count(),
            ];
            $beneficiariosProgress = $beneficiariosMetrics['total'] > 0
                ? round(($beneficiariosMetrics['registrados'] / max($beneficiariosMetrics['total'], 1)) * 100)
                : 0;

            $salud360Metrics = [
                'activos' => (int) \App\Models\Salud360Assignment::where('active', true)->count(),
                'sesionesSemana' => (int) \App\Models\Salud360Session::whereBetween('session_date', [$weekStart, $now])->count(),
                'proximas' => (int) \App\Models\Salud360Session::whereNotNull('next_session_date')
                    ->whereBetween('next_session_date', [$todayStart, $upcomingLimit])
                    ->distinct('beneficiario_id')
                    ->count('beneficiario_id'),
            ];

            $volMetrics = [
                'grupos' => (int) \App\Models\VolGroup::count(),
                'inscritos' => (int) \App\Models\VolEnrollment::active()->count(),
                'pagosMes' => (int) \App\Models\VolPayment::whereBetween('payment_date', [$monthStart, $monthEnd])->count(),
                'mesLabel' => $monthStart->translatedFormat('F Y'),
            ];
        @endphp

        <div class="row g-4">
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm h-100 text-dark border-0">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="h5 fw-semibold text-primary mb-0">{{ __('Beneficiarios') }}</h3>
                                <span class="badge bg-primary text-white">{{ __('Padrones') }}</span>
                            </div>
                            <p class="text-muted small mb-0">{{ __('Seguimiento de capturas y registros verificados en el sistema.') }}</p>
                        </div>
                        <div>
                            <div class="d-flex align-items-baseline gap-3">
                                <div class="display-5 fw-bold text-primary mb-0">{{ number_format($beneficiariosMetrics['total']) }}</div>
                                <span class="text-muted small">{{ __('Total activos') }}</span>
                            </div>
                            <div class="progress bg-body-tertiary mt-3" style="height: 6px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $beneficiariosProgress }}%;" aria-valuenow="{{ $beneficiariosProgress }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mt-2">
                                <span>{{ __('Registrados') }}</span>
                                <span class="fw-semibold text-success">{{ number_format($beneficiariosMetrics['registrados']) }}</span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>{{ __('En revisión') }}</span>
                                <span class="fw-semibold text-warning">{{ number_format($beneficiariosMetrics['borradores']) }}</span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>{{ __('Capturados hoy') }}</span>
                                <span class="fw-semibold">{{ number_format($beneficiariosMetrics['hoy']) }}</span>
                            </div>
                        </div>
                        <div class="mt-auto">
                            <a class="btn btn-primary w-100" href="{{ route('admin.beneficiarios.index') }}">
                                <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Ir al módulo') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm h-100 text-dark border-0">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="h5 fw-semibold text-info mb-0">{{ __('Salud 360') }}</h3>
                                <span class="badge bg-info text-dark">{{ __('Bienestar') }}</span>
                            </div>
                            <p class="text-muted small mb-0">{{ __('Estado de las asignaciones psicológicas y sesiones recientes.') }}</p>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-info bg-opacity-10">
                                    <div>
                                        <div class="small text-muted">{{ __('Pacientes activos') }}</div>
                                        <div class="h4 fw-semibold text-info mb-0">{{ number_format($salud360Metrics['activos']) }}</div>
                                    </div>
                                    <i class="bi bi-people-fill fs-3 text-info"></i>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted">{{ __('Sesiones esta semana') }}</div>
                                    <div class="h4 fw-semibold text-primary mb-0">{{ number_format($salud360Metrics['sesionesSemana']) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted">{{ __('Próximas citas (7 días)') }}</div>
                                    <div class="h4 fw-semibold text-primary mb-0">{{ number_format($salud360Metrics['proximas']) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-auto">
                            <a class="btn btn-info text-dark w-100" href="{{ route('s360.admin.dash') }}">
                                <i class="bi bi-heart-pulse me-1"></i>{{ __('Ir al módulo') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm h-100 text-dark border-0">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="h5 fw-semibold text-warning mb-0">{{ __('Jóvenes al Volante') }}</h3>
                                <span class="badge bg-warning text-dark">{{ __('Movilidad') }}</span>
                            </div>
                            <p class="text-muted small mb-0">{{ __('Resumen de grupos activos, inscripciones y pagos del mes.') }}</p>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted">{{ __('Grupos activos') }}</div>
                                    <div class="h4 fw-semibold text-warning mb-0">{{ number_format($volMetrics['grupos']) }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted">{{ __('Participantes inscritos') }}</div>
                                    <div class="h4 fw-semibold text-warning mb-0">{{ number_format($volMetrics['inscritos']) }}</div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small text-muted">{{ __('Pagos registrados en :period', ['period' => $volMetrics['mesLabel']]) }}</div>
                                            <div class="h4 fw-semibold text-warning mb-0">{{ number_format($volMetrics['pagosMes']) }}</div>
                                        </div>
                                        <i class="bi bi-receipt fs-3 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-auto">
                            <a class="btn btn-warning text-dark w-100" href="{{ route('vol.dashboard') }}">
                                <i class="bi bi-steering-wheel me-1"></i>{{ __('Ir al módulo') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @elseif($isPsychologist)
        <div class="card">
            <div class="card-body" id="agenda-container">
                <div class="text-muted">{{ __('Cargando…') }}</div>
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
                    container.innerHTML = '<div class="text-muted">{{ __('No hay citas para esta semana.') }}</div>';
                    return;
                }
                const rows = items.map(item => {
                    const badgeClass = item.estado === 'atendido' ? 'bg-success' : 'bg-warning text-dark';
                    const estadoText = item.estado === 'atendido' ? '{{ __('Atendido') }}' : '{{ __('Pendiente') }}';
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
                document.getElementById('agenda-container').innerHTML = '<div class="text-danger">{{ __('Error al cargar la agenda.') }}</div>';
            }
        });
        </script>
    @else
        <div class="card">
            <div class="card-body">
                {{ __("You're logged in!") }}
            </div>
        </div>
    @endif
</x-app-layout>
