@php
    $evento = $evento ?? null;
@endphp

@if ($errors->any())
    <div class="alert alert-danger"><strong>Revisa el formulario</strong></div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label for="evento_tipo_id" class="form-label">Tipo de evento</label>
        <select id="evento_tipo_id" name="evento_tipo_id" class="form-select @error('evento_tipo_id') is-invalid @enderror" required>
            <option value="" disabled @selected(! old('evento_tipo_id', $evento->evento_tipo_id ?? null))>Selecciona una opcion</option>
            @foreach($tipos as $tipo)
                <option value="{{ $tipo->id }}" @selected((string) old('evento_tipo_id', $evento->evento_tipo_id ?? '') === (string) $tipo->id)>
                    {{ $tipo->nombre }}{{ isset($tipo->activo) && ! $tipo->activo ? ' (inactivo)' : '' }}
                </option>
            @endforeach
        </select>
        @error('evento_tipo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="municipio_id" class="form-label">Municipio</label>
        <select id="municipio_id" name="municipio_id" class="form-select @error('municipio_id') is-invalid @enderror" required>
            <option value="" disabled @selected(! old('municipio_id', $evento->municipio_id ?? null))>Selecciona un municipio</option>
            @foreach($municipios as $municipio)
                <option value="{{ $municipio->id }}" @selected((string) old('municipio_id', $evento->municipio_id ?? '') === (string) $municipio->id)>
                    {{ $municipio->nombre }}
                </option>
            @endforeach
        </select>
        @error('municipio_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8">
        <label for="lugar" class="form-label">Lugar o sede</label>
        <input id="lugar" name="lugar" value="{{ old('lugar', $evento->lugar ?? '') }}" class="form-control @error('lugar') is-invalid @enderror" required>
        @error('lugar')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="rol_participacion" class="form-label">Participacion</label>
        <select id="rol_participacion" name="rol_participacion" class="form-select @error('rol_participacion') is-invalid @enderror" required>
            @foreach($rolesParticipacion as $value => $label)
                <option value="{{ $value }}" @selected(old('rol_participacion', $evento->rol_participacion ?? 'anfitrion') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('rol_participacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="total_asistentes" class="form-label">Total de beneficiarios o asistentes</label>
        <input id="total_asistentes" name="total_asistentes" type="number" min="0" value="{{ old('total_asistentes', $evento->total_asistentes ?? 0) }}" class="form-control @error('total_asistentes') is-invalid @enderror" required>
        @error('total_asistentes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8">
        <label for="evidencia_url" class="form-label">Link Drive de evidencias</label>
        <input id="evidencia_url" name="evidencia_url" type="url" value="{{ old('evidencia_url', $evento->evidencia_url ?? '') }}" class="form-control @error('evidencia_url') is-invalid @enderror" placeholder="https://drive.google.com/...">
        @error('evidencia_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="descripcion" class="form-label">De que trato</label>
        <textarea id="descripcion" name="descripcion" rows="5" class="form-control @error('descripcion') is-invalid @enderror" required>{{ old('descripcion', $evento->descripcion ?? '') }}</textarea>
        @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
