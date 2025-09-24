@php($d = $domicilio ?? null)
@if ($errors->any())
    <div class="alert alert-danger"><strong>Revisa el formulario</strong></div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Beneficiario</label>
        <select name="beneficiario_id" class="form-select @error('beneficiario_id') is-invalid @enderror" required>
            <option value="" disabled {{ old('beneficiario_id', $d->beneficiario_id ?? '')=='' ? 'selected' : '' }}>Selecciona...</option>
            @foreach($beneficiarios as $b)
                <option value="{{ $b->id }}" @selected(old('beneficiario_id', $d->beneficiario_id ?? '')==$b->id)>
                    {{ $b->folio_tarjeta }} - {{ $b->nombre }} {{ $b->apellido_paterno }} {{ $b->apellido_materno }}
                </option>
            @endforeach
        </select>
        @error('beneficiario_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Selecciona el beneficiario al que pertenece el domicilio.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Calle</label>
        <input name="calle" value="{{ old('calle', $d->calle ?? '') }}" class="form-control @error('calle') is-invalid @enderror" placeholder="Ej. Av. Reforma" required>
        @error('calle')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Número ext</label>
        <input name="numero_ext" value="{{ old('numero_ext', $d->numero_ext ?? '') }}" class="form-control @error('numero_ext') is-invalid @enderror" placeholder="Ej. 123" required>
        @error('numero_ext')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Número int</label>
        <input name="numero_int" value="{{ old('numero_int', $d->numero_int ?? '') }}" class="form-control @error('numero_int') is-invalid @enderror" placeholder="Opcional">
        @error('numero_int')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Colonia</label>
        <input name="colonia" value="{{ old('colonia', $d->colonia ?? '') }}" class="form-control @error('colonia') is-invalid @enderror" placeholder="Ej. Centro" required>
        @error('colonia')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Municipio (texto)</label>
        <input name="municipio" value="{{ old('municipio', $d->municipio ?? '') }}" class="form-control @error('municipio') is-invalid @enderror" placeholder="Nombre del municipio" required>
        @error('municipio')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">CP</label>
        <input name="codigo_postal" value="{{ old('codigo_postal', $d->codigo_postal ?? '') }}" class="form-control @error('codigo_postal') is-invalid @enderror" placeholder="Código postal" required>
        @error('codigo_postal')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Seccional</label>
        <input name="seccional" value="{{ old('seccional', $d->seccional ?? '') }}" class="form-control @error('seccional') is-invalid @enderror" placeholder="Ej. 0001" required>
        @error('seccional')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

