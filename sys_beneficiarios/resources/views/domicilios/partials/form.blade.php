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
        <label class="form-label">Municipio (autocompletado)</label>
        <select name="municipio_id" id="domicilio-form-municipio" class="form-select @error('municipio_id') is-invalid @enderror">
            <option value="">Selecciona o deja que se asigne automaticamente</option>
            @foreach($municipios as $id => $nombre)
                <option value="{{ $id }}" @selected(old('municipio_id', $d->municipio_id ?? '')==$id)>{{ $nombre }}</option>
            @endforeach
        </select>
        <div class="form-text">Se rellena al validar la seccional.</div>
        @error('municipio_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">CP</label>
        <input name="codigo_postal" value="{{ old('codigo_postal', $d->codigo_postal ?? '') }}" class="form-control @error('codigo_postal') is-invalid @enderror" placeholder="Código postal" required>
        @error('codigo_postal')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Seccional</label>
        <input id="domicilio-form-seccional" name="seccional" value="{{ old('seccional', optional($d?->seccion)->seccional ?? '') }}" class="form-control @error('seccional') is-invalid @enderror" placeholder="Ej. 0001" required>
        @error('seccional')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Distritos detectados</label>
        <div class="bg-dark border border-white border-opacity-25 rounded-3 p-3" id="domicilio-form-seccion-summary">
            <div class="small text-white-50">Municipio</div>
            <div class="fw-semibold" id="domicilio-form-seccional-muni">-</div>
            <div class="small text-white-50 mt-2">DL / DF</div>
            <div class="fw-semibold" id="domicilio-form-seccional-distritos">-</div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const seccionInput = document.getElementById('domicilio-form-seccional');
    const municipioSelect = document.getElementById('domicilio-form-municipio');
    const summaryCard = document.getElementById('domicilio-form-seccion-summary');
    const summaryMun = document.getElementById('domicilio-form-seccional-muni');
    const summaryDist = document.getElementById('domicilio-form-seccional-distritos');

    const renderSummary = (municipio = '-', dl = '-', df = '-') => {
        if (summaryMun) summaryMun.textContent = municipio || '-';
        if (summaryDist) summaryDist.textContent = `DL: ${dl || '--'} · DF: ${df || '--'}`;
    };

    const toggleSummary = (active) => {
        summaryCard?.classList.toggle('border-success', !!active);
        summaryCard?.classList.toggle('border-white', !active);
    };

    const applyData = (data) => {
        if (!data) return;
        if (municipioSelect) municipioSelect.value = data.municipio_id ? String(data.municipio_id) : '';
        renderSummary(data.municipio || '-', data.distrito_local || '-', data.distrito_federal || '-');
        toggleSummary(true);
    };

    const clearData = () => {
        if (municipioSelect) municipioSelect.value = '';
        renderSummary('-', '-', '-');
        toggleSummary(false);
    };

    const hydrate = async (value) => {
        const query = (value || '').trim();
        if (!query) {
            clearData();
            return;
        }
        try {
            const res = await fetch(`/api/secciones/${encodeURIComponent(query)}`, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) {
                clearData();
                return;
            }
            const payload = await res.json();
            applyData(payload);
        } catch (_) {
            clearData();
        }
    };

    if (seccionInput) {
        seccionInput.addEventListener('input', (e) => {
            clearTimeout(seccionInput.__timer);
            seccionInput.__timer = setTimeout(() => hydrate(e.target.value), 300);
        });
        seccionInput.addEventListener('change', (e) => hydrate(e.target.value));
        seccionInput.addEventListener('blur', (e) => hydrate(e.target.value));
        if (seccionInput.value) {
            hydrate(seccionInput.value);
        } else {
            clearData();
        }
    }
});
</script>
@endpush
