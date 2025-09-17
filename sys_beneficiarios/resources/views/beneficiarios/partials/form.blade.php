@php($b = $beneficiario ?? null)
@if ($errors->any())
    <div class="alert alert-danger"><strong>Revisa el formulario</strong></div>
@endif

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Folio tarjeta</label>
        <input name="folio_tarjeta" value="{{ old('folio_tarjeta', $b->folio_tarjeta ?? '') }}" class="form-control @error('folio_tarjeta') is-invalid @enderror" required>
        @error('folio_tarjeta')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Nombre</label>
        <input name="nombre" value="{{ old('nombre', $b->nombre ?? '') }}" class="form-control @error('nombre') is-invalid @enderror" required>
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Apellido paterno</label>
        <input name="apellido_paterno" value="{{ old('apellido_paterno', $b->apellido_paterno ?? '') }}" class="form-control @error('apellido_paterno') is-invalid @enderror" required>
        @error('apellido_paterno')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Apellido materno</label>
        <input name="apellido_materno" value="{{ old('apellido_materno', $b->apellido_materno ?? '') }}" class="form-control @error('apellido_materno') is-invalid @enderror" required>
        @error('apellido_materno')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">CURP</label>
        <input name="curp" value="{{ old('curp', $b->curp ?? '') }}" maxlength="18" minlength="18" class="form-control text-uppercase @error('curp') is-invalid @enderror" required>
        @error('curp')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Fecha nacimiento</label>
        <input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', isset($b)? optional($b->fecha_nacimiento)->format('Y-m-d') : '') }}" class="form-control @error('fecha_nacimiento') is-invalid @enderror" required>
        @error('fecha_nacimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Edad (auto)</label>
        <input type="number" name="edad" value="{{ old('edad', $b->edad ?? '') }}" class="form-control" readonly>
    </div>
    <div class="col-md-2">
        <label class="form-label">Sexo</label>
        <select name="sexo" class="form-select @error('sexo') is-invalid @enderror">
            <option value="">—</option>
            @foreach(['M'=>'M','F'=>'F','X'=>'X'] as $key=>$val)
                <option value="{{ $key }}" @selected(old('sexo', $b->sexo ?? '')==$key)>{{ $val }}</option>
            @endforeach
        </select>
        @error('sexo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2 d-flex align-items-center">
        <div class="form-check mt-4">
            <input type="hidden" name="discapacidad" value="0">
            <input class="form-check-input" type="checkbox" name="discapacidad" value="1" @checked(old('discapacidad', $b->discapacidad ?? false))>
            <label class="form-check-label">Discapacidad</label>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label">ID INE</label>
        <input name="id_ine" value="{{ old('id_ine', $b->id_ine ?? '') }}" class="form-control @error('id_ine') is-invalid @enderror" required>
        @error('id_ine')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Teléfono (10 dígitos)</label>
        <input name="telefono" value="{{ old('telefono', $b->telefono ?? '') }}" class="form-control @error('telefono') is-invalid @enderror" required>
        @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    
    
    
</div>

<hr class="my-4">
<h5>Domicilio</h5>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Calle</label>
        <input name="domicilio[calle]" value="{{ old('domicilio.calle', $domicilio->calle ?? '') }}" class="form-control @error('domicilio.calle') is-invalid @enderror">
        @error('domicilio.calle')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Número ext</label>
        <input name="domicilio[numero_ext]" value="{{ old('domicilio.numero_ext', $domicilio->numero_ext ?? '') }}" class="form-control @error('domicilio.numero_ext') is-invalid @enderror">
        @error('domicilio.numero_ext')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">Número int</label>
        <input name="domicilio[numero_int]" value="{{ old('domicilio.numero_int', $domicilio->numero_int ?? '') }}" class="form-control @error('domicilio.numero_int') is-invalid @enderror">
        @error('domicilio.numero_int')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Colonia</label>
        <input name="domicilio[colonia]" value="{{ old('domicilio.colonia', $domicilio->colonia ?? '') }}" class="form-control @error('domicilio.colonia') is-invalid @enderror">
        @error('domicilio.colonia')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    
    <div class="col-md-2">
        <label class="form-label">CP</label>
        <input name="domicilio[codigo_postal]" value="{{ old('domicilio.codigo_postal', $domicilio->codigo_postal ?? '') }}" class="form-control @error('domicilio.codigo_postal') is-invalid @enderror">
        @error('domicilio.codigo_postal')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Seccional</label>
        <input id="dom-seccional" name="domicilio[seccional]" value="{{ old('domicilio.seccional', $domicilio->seccional ?? '') }}" class="form-control @error('domicilio.seccional') is-invalid @enderror">
        @error('domicilio.seccional')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Municipio</label>
        <select id="dom-municipio-id" name="domicilio[municipio_id]" class="form-select @error('domicilio.municipio_id') is-invalid @enderror">
            <option value="">—</option>
            @foreach($municipios as $id=>$nombre)
                <option value="{{ $id }}" @selected(old('domicilio.municipio_id', $domicilio->municipio_id ?? ($b->municipio_id ?? ''))==$id)>{{ $nombre }}</option>
            @endforeach
        </select>
        @error('domicilio.municipio_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const secc = document.getElementById('dom-seccional');
  // Campos de distritos ahora se calculan en backend; no hay inputs visibles
  const munSel = document.getElementById('dom-municipio-id');
  if (!secc) return;

  const applyData = (data) => {
    if (!data) return;
    if (munSel && data.municipio_id) munSel.value = String(data.municipio_id);
  };
  const clearData = () => applyData({ municipio_id: '' });

  let timer = null;
  const debounced = (fn, wait=400) => (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); };

  const fetchDistritos = async (val) => {
    const query = (val || '').trim();
    if (!query) { clearData(); return; }
    try {
      const res = await fetch(`/api/secciones/${encodeURIComponent(query)}`, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) { clearData(); return; }
      const data = await res.json();
      applyData(data);
    } catch (_) { clearData(); }
  };

  const debouncedFetch = debounced(fetchDistritos, 400);
  secc.addEventListener('input', (e) => debouncedFetch(e.target.value));
  secc.addEventListener('change', (e) => fetchDistritos(e.target.value));
  secc.addEventListener('blur', (e) => fetchDistritos(e.target.value));
});
</script>
@endpush
