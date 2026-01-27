@php
    $p = $programa ?? null;
@endphp

@if ($errors->any())
    <div class="alert alert-danger"><strong>Revisa el formulario</strong></div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label for="nombre" class="form-label">Nombre</label>
        <input id="nombre" name="nombre" value="{{ old('nombre', $p->nombre ?? '') }}" class="form-control @error('nombre') is-invalid @enderror" required>
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="slug" class="form-label">Slug</label>
        <input id="slug" name="slug" value="{{ old('slug', $p->slug ?? '') }}" class="form-control @error('slug') is-invalid @enderror" placeholder="ej: clases-guitarra">
        <div class="form-text">Opcional, si se deja en blanco se genera automaticamente.</div>
        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="tipo_periodo" class="form-label">Tipo de periodo</label>
        <select id="tipo_periodo" name="tipo_periodo" class="form-select @error('tipo_periodo') is-invalid @enderror" required>
            @foreach(['mensual' => 'Mensual', 'unico' => 'Unico', 'anual' => 'Anual'] as $key => $label)
                <option value="{{ $key }}" @selected(old('tipo_periodo', $p->tipo_periodo ?? 'mensual') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('tipo_periodo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 d-flex align-items-center">
        <div class="form-check mt-4">
            <input type="hidden" name="renovable" value="0">
            <input id="renovable" class="form-check-input" type="checkbox" name="renovable" value="1" @checked(old('renovable', $p->renovable ?? false))>
            <label for="renovable" class="form-check-label">Permite renovacion</label>
        </div>
    </div>
    <div class="col-md-4 d-flex align-items-center">
        <div class="form-check mt-4">
            <input type="hidden" name="activo" value="0">
            <input id="activo" class="form-check-input" type="checkbox" name="activo" value="1" @checked(old('activo', $p->activo ?? true))>
            <label for="activo" class="form-check-label">Activo</label>
        </div>
    </div>
</div>
