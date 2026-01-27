<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between">
            <h2 class="h4 m-0">Dashboard de inscripciones</h2>
            <a href="{{ route('inscripciones.list') }}" class="btn btn-outline-secondary">Ver listado</a>
        </div>
    </x-slot>

    <div data-kpis-url="{{ route('inscripciones.kpis', absolute: false) }}">
        <div class="card mb-3">
            <div class="card-body">
                <form id="kpiFilters" class="row gy-2 gx-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Programa</label>
                        <select name="programa_id" class="form-select">
                            <option value="">-</option>
                            @foreach($programas as $programa)
                                <option value="{{ $programa->id }}">{{ $programa->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="month" name="from" class="form-control">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="month" name="to" class="form-control">
                    </div>
                    <div class="col-12 col-md-2 text-end">
                        <button class="btn btn-primary" type="submit">Aplicar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Total</div>
                        <div class="h3" id="kpiTotal">-</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Mes actual</div>
                        <div class="h3" id="kpiCurrentMonth">-</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted">Rango</div>
                        <div class="h6" id="kpiRange">-</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">Inscritos por mes</div>
                    <div class="card-body">
                        <canvas id="chartMonthly" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">Por programa</div>
                    <div class="card-body">
                        <canvas id="chartByPrograma" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/inscripciones-dashboard.js'])
</x-app-layout>
