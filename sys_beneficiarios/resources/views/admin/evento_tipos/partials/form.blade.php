@php
    $tipo = $eventoTipo ?? null;
@endphp

@if ($errors->any())
    <div class="alert alert-danger"><strong>Revisa el formulario</strong></div>
@endif

<div class="row g-3">
    <div class="col-md-8">
        <label for="nombre" class="form-label">Nombre</label>
        <input id="nombre" name="nombre" value="{{ old('nombre', $tipo->nombre ?? '') }}" class="form-control @error('nombre') is-invalid @enderror" required>
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 d-flex align-items-center">
        <div class="form-check mt-4">
            <input type="hidden" name="activo" value="0">
            <input id="activo" class="form-check-input" type="checkbox" name="activo" value="1" @checked(old('activo', $tipo->activo ?? true))>
            <label for="activo" class="form-check-label">Activo</label>
        </div>
    </div>
</div>
