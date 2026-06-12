@php
    $checks = $statusSummary['checks'] ?? [];
    $counters = $statusSummary['counters'] ?? [];
    $latestRun = $statusSummary['latest']['run'] ?? null;
    $latestInbound = $statusSummary['latest']['inbound'] ?? null;
    $readyCount = collect($checks)->where('ready', true)->count();
    $totalChecks = count($checks);
@endphp

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Readiness operativo</span>
                <span class="badge {{ $readyCount === $totalChecks ? 'bg-success' : 'bg-warning text-dark' }}">
                    {{ $readyCount }}/{{ $totalChecks }} checks
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($checks as $check)
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 {{ $check['ready'] ? 'border-success-subtle' : 'border-warning-subtle' }}">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="fw-semibold">{{ $check['label'] }}</div>
                                    <span class="badge {{ $check['ready'] ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ $check['ready'] ? 'OK' : 'Pendiente' }}
                                    </span>
                                </div>
                                <div class="text-white-50 small mt-2">{{ $check['detail'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">Indicadores</div>
            <div class="card-body d-grid gap-3">
                <div class="d-flex justify-content-between">
                    <span class="text-white-50">Corridas totales</span>
                    <strong>{{ $counters['sync_runs_total'] ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-white-50">Corridas fallidas</span>
                    <strong>{{ $counters['sync_runs_failed'] ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-white-50">Corridas parciales</span>
                    <strong>{{ $counters['sync_runs_partial'] ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-white-50">Inbound total</span>
                    <strong>{{ $counters['inbound_total'] ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-white-50">Inbound failed</span>
                    <strong>{{ $counters['inbound_failed'] ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-white-50">Inbound rejected</span>
                    <strong>{{ $counters['inbound_rejected'] ?? 0 }}</strong>
                </div>
                <hr class="border-secondary-subtle my-1">
                <div>
                    <div class="text-white-50 small text-uppercase mb-1">Ultima corrida</div>
                    @if($latestRun)
                        <div class="fw-semibold">{{ $latestRun->status }}</div>
                        <div class="small text-white-50">{{ optional($latestRun->created_at)->format('Y-m-d H:i') }}</div>
                    @else
                        <div class="text-white-50 small">Sin corridas registradas.</div>
                    @endif
                </div>
                <div>
                    <div class="text-white-50 small text-uppercase mb-1">Ultimo inbound</div>
                    @if($latestInbound)
                        <div class="fw-semibold">{{ $latestInbound->status }}</div>
                        <div class="small text-white-50">{{ optional($latestInbound->received_at)->format('Y-m-d H:i') }}</div>
                    @else
                        <div class="text-white-50 small">Sin requests inbound.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
