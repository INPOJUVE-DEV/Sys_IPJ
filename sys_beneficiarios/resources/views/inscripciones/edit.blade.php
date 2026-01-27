<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Editar inscripcion</h2></x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-column gap-1">
                <div class="text-white-50 small">Beneficiario</div>
                <div class="fw-semibold">
                    {{ $inscripcion->beneficiario->nombre }} {{ $inscripcion->beneficiario->apellido_paterno }} {{ $inscripcion->beneficiario->apellido_materno }}
                </div>
                <div class="text-white-50 small">CURP: {{ $inscripcion->beneficiario->curp }}</div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('inscripciones.update', $inscripcion) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="programa_id" class="form-label">Programa</label>
                        <select id="programa_id" name="programa_id" class="form-select @error('programa_id') is-invalid @enderror" required>
                            @foreach($programas as $programa)
                                <option value="{{ $programa->id }}" @selected(old('programa_id', $inscripcion->programa_id) == $programa->id)>
                                    {{ $programa->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('programa_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="periodo" class="form-label">Periodo</label>
                        <input id="periodo" type="month" name="periodo" value="{{ old('periodo', $inscripcion->periodo) }}" class="form-control @error('periodo') is-invalid @enderror" required>
                        @error('periodo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="estatus" class="form-label">Estatus</label>
                        <select id="estatus" name="estatus" class="form-select @error('estatus') is-invalid @enderror" required>
                            @foreach(['inscrito' => 'Inscrito', 'baja' => 'Baja', 'lista_espera' => 'Lista de espera'] as $key => $label)
                                <option value="{{ $key }}" @selected(old('estatus', $inscripcion->estatus) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('estatus')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <a href="{{ route('inscripciones.list') }}" class="btn btn-outline-secondary me-2"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                    <button class="btn btn-cta" type="submit"><i class="bi bi-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
