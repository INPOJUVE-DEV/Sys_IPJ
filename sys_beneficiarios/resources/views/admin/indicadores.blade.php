<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h4 m-0">Indicadores</h2>
                <div class="text-muted small">Seguimiento diario de beneficiarios, inscripciones y eventos dentro del mes seleccionado.</div>
            </div>
            <a href="{{ route('admin.home') }}" class="btn btn-outline-secondary">Volver al dashboard</a>
        </div>
    </x-slot>

    <div data-indicators-url="{{ route('admin.indicadores.data', absolute: false) }}">
        <div class="card mb-3">
            <div class="card-body">
                <form id="indicatorFilters" class="row gy-2 gx-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Mes</label>
                        <input type="month" name="month" class="form-control" value="{{ now()->format('Y-m') }}">
                    </div>
                    <div class="col-12 col-md-8 text-md-end">
                        <button class="btn btn-primary" type="submit">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Beneficiarios del mes</div>
                        <div class="h3" id="indicatorBeneficiarios">-</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Inscripciones del mes</div>
                        <div class="h3" id="indicatorInscripciones">-</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Eventos del mes</div>
                        <div class="h3" id="indicatorEventos">-</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Rango diario</div>
                        <div class="small fw-semibold" id="indicatorRange">-</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Resultados del dia primero al ultimo dia del mes</div>
            <div class="card-body">
                <canvas id="indicatorsChart" height="110"></canvas>
            </div>
        </div>
    </div>

    @vite(['resources/js/admin-indicators.js'])
</x-app-layout>
