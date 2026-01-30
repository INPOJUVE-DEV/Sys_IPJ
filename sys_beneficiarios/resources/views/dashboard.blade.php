@php
    $user = auth()->user();
    $isAdmin = $user?->hasRole('admin');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <h2 class="h4 mb-0">
                {{ $isAdmin ? __('Centro de control') : __('Dashboard') }}
            </h2>
            @if($isAdmin)
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('beneficiarios.create') }}" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-person-plus-fill me-1"></i>{{ __('Nuevo beneficiario') }}
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
            $weeks = [];
            for ($i = 3; $i >= 0; $i--) {
                $start = $weekStart->copy()->subWeeks($i);
                $weeks[] = [
                    'start' => $start,
                    'end' => $start->copy()->endOfWeek(),
                    'label' => $start->format('Y-m-d'),
                ];
            }

            $ageMin = 17;
            $ageMax = 28;
            $dobMax = $now->copy()->subYears($ageMin)->toDateString();
            $dobMin = $now->copy()->subYears($ageMax + 1)->addDay()->toDateString();

            $beneficiariosMetrics = [
                'total' => (int) \App\Models\Beneficiario::count(),
                'hoy' => (int) \App\Models\Beneficiario::whereBetween('created_at', [$todayStart, $now])->count(),
                'ultimaSemana' => (int) \App\Models\Beneficiario::whereBetween('created_at', [$weekStart, $now])->count(),
                'conDiscapacidad' => (int) \App\Models\Beneficiario::where('discapacidad', true)->count(),
                'poblacionObjetivo' => (int) \App\Models\Beneficiario::whereBetween('fecha_nacimiento', [$dobMin, $dobMax])->count(),
                'hombres' => (int) \App\Models\Beneficiario::where('sexo', 'M')->count(),
                'mujeres' => (int) \App\Models\Beneficiario::where('sexo', 'F')->count(),
            ];

            $periodoActual = request('periodo') ?: $now->format('Y-m');
            if (!preg_match('/^\d{4}-\d{2}$/', $periodoActual)) {
                $periodoActual = $now->format('Y-m');
            }
            $programas = \App\Models\Programa::orderBy('nombre')->get(['id', 'nombre']);
            $inscritosPorPrograma = \App\Models\Inscripcion::where('periodo', $periodoActual)
                ->selectRaw('programa_id, COUNT(*) as c')
                ->groupBy('programa_id')
                ->pluck('c', 'programa_id');

            $capturistas = \App\Models\User::role(['capturista', 'capturista_programas'])->orderBy('name')->get(['uuid', 'name']);
            $capturistasByWeek = [];
            foreach ($weeks as $index => $range) {
                $capturistasByWeek[$index] = \App\Models\Beneficiario::whereBetween('created_at', [$range['start'], $range['end']])
                    ->selectRaw('created_by, COUNT(*) as c')
                    ->groupBy('created_by')
                    ->pluck('c', 'created_by');
            }
        @endphp

        <div class="row g-4">
            <div class="col-12 col-xl-6">
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
                            <div class="row g-2 mt-3">
                                <div class="col-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="small text-muted">{{ __('Capturados hoy') }}</div>
                                        <div class="h5 fw-semibold text-primary mb-0">{{ number_format($beneficiariosMetrics['hoy']) }}</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="small text-muted">{{ __('Ultimos 7 dias') }}</div>
                                        <div class="h5 fw-semibold text-primary mb-0">{{ number_format($beneficiariosMetrics['ultimaSemana']) }}</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small text-muted">{{ __('Personas con discapacidad') }}</div>
                                                <div class="h5 fw-semibold text-primary mb-0">{{ number_format($beneficiariosMetrics['conDiscapacidad']) }}</div>
                                            </div>
                                            <i class="bi bi-universal-access-circle fs-3 text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="border rounded-3 p-3">
                                        <div class="row g-2 text-center">
                                            <div class="col-6">
                                                <div class="small text-muted">{{ __('Hombres') }}</div>
                                                <div class="h5 fw-semibold text-primary mb-0">{{ number_format($beneficiariosMetrics['hombres']) }}</div>
                                            </div>
                                            <div class="col-6 border-start">
                                                <div class="small text-muted">{{ __('Mujeres') }}</div>
                                                <div class="h5 fw-semibold text-primary mb-0">{{ number_format($beneficiariosMetrics['mujeres']) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-auto">
                            <a class="btn btn-primary w-100" href="{{ route('admin.beneficiarios.index') }}">
                                <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Ir al modulo') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card shadow-sm h-100 text-dark border-0">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="h5 fw-semibold text-primary mb-0">{{ __('Población objetivo') }}</h3>
                                <span class="badge bg-primary text-white">{{ __('17 a 28 años') }}</span>
                            </div>
                            <p class="text-muted small mb-0">{{ __('Beneficiarios dentro del rango de edad 17 a 28 años.') }}</p>
                        </div>
                        <div class="d-flex align-items-baseline gap-3">
                            <div class="display-5 fw-bold text-primary mb-0">{{ number_format($beneficiariosMetrics['poblacionObjetivo']) }}</div>
                            <span class="text-muted small">{{ __('Total en rango') }}</span>
                        </div>
                        <div class="mt-auto">
                            <a class="btn btn-outline-primary w-100" href="{{ route('admin.beneficiarios.index', ['edad_min' => 17, 'edad_max' => 28]) }}">
                                <i class="bi bi-funnel-fill me-1"></i>{{ __('Ver beneficiarios') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card shadow-sm h-100 text-dark border-0">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <h3 class="h5 fw-semibold text-primary mb-0">{{ __('Inscritos por programa') }}</h3>
                                    <span class="badge bg-primary text-white">{{ $periodoActual }}</span>
                                </div>
                                <form method="GET" action="{{ url()->current() }}" class="d-flex align-items-center gap-2">
                                    <label for="periodo_inscripciones" class="visually-hidden">{{ __('Periodo') }}</label>
                                    <input id="periodo_inscripciones" type="month" name="periodo" value="{{ $periodoActual }}" class="form-control form-control-sm">
                                    <button class="btn btn-sm btn-primary" type="submit">{{ __('Ver') }}</button>
                                </form>
                            </div>
                            <p class="text-muted small mb-0">{{ __('Cantidad de inscritos del mes por programa.') }}</p>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>{{ __('Programa') }}</th>
                                        <th class="text-end">{{ __('Inscritos') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($programas as $programa)
                                        @php $total = (int) ($inscritosPorPrograma[$programa->id] ?? 0); @endphp
                                        <tr>
                                            <td class="fw-semibold">{{ $programa->nombre }}</td>
                                            <td class="text-end">{{ number_format($total) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted py-3">{{ __('Sin programas registrados') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-auto">
                            <a class="btn btn-outline-primary w-100" href="{{ route('inscripciones.list', ['periodo' => $periodoActual]) }}">
                                <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Ver inscripciones del mes') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card shadow-sm h-100 text-dark border-0">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="h5 fw-semibold text-primary mb-0">{{ __('Capturistas por semana') }}</h3>
                                <span class="badge bg-primary text-white">{{ __('Ultimas 4 semanas') }}</span>
                            </div>
                            <p class="text-muted small mb-0">{{ __('Total de beneficiarios capturados por cada usuario.') }}</p>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>{{ __('Capturista') }}</th>
                                        @foreach($weeks as $week)
                                            <th class="text-end">{{ $week['label'] }}</th>
                                        @endforeach
                                        <th class="text-end">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($capturistas as $capturista)
                                        @php
                                            $rowTotal = 0;
                                        @endphp
                                        <tr>
                                            <td class="fw-semibold">{{ $capturista->name }}</td>
                                            @foreach($weeks as $index => $week)
                                                @php
                                                    $value = (int) ($capturistasByWeek[$index][$capturista->uuid] ?? 0);
                                                    $rowTotal += $value;
                                                @endphp
                                                <td class="text-end">{{ number_format($value) }}</td>
                                            @endforeach
                                            <td class="text-end fw-semibold">{{ number_format($rowTotal) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ count($weeks) + 2 }}" class="text-center text-muted py-3">{{ __('Sin capturistas registrados') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body">
                {{ __("You're logged in!") }}
            </div>
        </div>
    @endif
</x-app-layout>
