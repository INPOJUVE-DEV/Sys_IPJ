<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 m-0">Detalle de registro</h2>
            <div>
                <a href="{{ route('mis-registros.index') }}" class="btn btn-outline-secondary">Volver</a>
                <a href="{{ route('mis-registros.edit', $beneficiario) }}" class="btn btn-primary">Editar</a>
            </div>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Datos personales</div>
                <div class="card-body">
                    @include('beneficiarios.partials.detail', ['beneficiario' => $beneficiario])
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Domicilio</div>
                <div class="card-body">
                    @php($d = $beneficiario->domicilio)
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Calle</dt><dd class="col-sm-8">{{ $d?->calle }} {{ $d?->numero_ext }} {{ $d?->numero_int ? 'Int '.$d->numero_int : '' }}</dd>
                        <dt class="col-sm-4">Colonia</dt><dd class="col-sm-8">{{ $d?->colonia }}</dd>
                        <dt class="col-sm-4">Municipio</dt><dd class="col-sm-8">{{ optional($d?->municipio)->nombre }}</dd>
                        <dt class="col-sm-4">CP</dt><dd class="col-sm-8">{{ $d?->codigo_postal }}</dd>
                        <dt class="col-sm-4">Seccional</dt><dd class="col-sm-8">{{ optional($d?->seccion)->seccional }}</dd>
                    </dl>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header">Activity log (Últimos 10)</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @forelse($activities as $act)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span>{{ $act->description }} @if($act->event) <span class="text-muted">({{ $act->event }})</span>@endif</span>
                                    <small class="text-muted">{{ $act->created_at->format('Y-m-d H:i') }}</small>
                                </div>
                                @if($act->causer)
                                    <div class="small text-muted">por: {{ optional($act->causer)->name ?? ('ID '.$act->causer_id) }}</div>
                                @endif
                            </li>
                        @empty
                            <li class="list-group-item text-muted">Sin eventos</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
