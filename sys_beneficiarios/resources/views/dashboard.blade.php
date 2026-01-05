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

            $beneficiariosMetrics = [
                'total' => (int) \App\Models\Beneficiario::count(),
                'hoy' => (int) \App\Models\Beneficiario::whereBetween('created_at', [$todayStart, $now])->count(),
                'ultimaSemana' => (int) \App\Models\Beneficiario::whereBetween('created_at', [$weekStart, $now])->count(),
                'conDiscapacidad' => (int) \App\Models\Beneficiario::where('discapacidad', true)->count(),
            ];
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
        </div>
    @else
        <div class="card">
            <div class="card-body">
                {{ __("You're logged in!") }}
            </div>
        </div>
    @endif
</x-app-layout>
