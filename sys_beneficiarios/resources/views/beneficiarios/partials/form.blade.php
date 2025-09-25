@php
    $b = $beneficiario ?? null;
    $fieldLabels = [
        'folio_tarjeta' => 'Folio tarjeta',
        'nombre' => 'Nombre',
        'apellido_paterno' => 'Apellido paterno',
        'apellido_materno' => 'Apellido materno',
        'curp' => 'CURP',
        'fecha_nacimiento' => 'Fecha de nacimiento',
        'sexo' => 'Sexo',
        'discapacidad' => 'Discapacidad',
        'id_ine' => 'ID INE',
        'telefono' => 'Telefono',
        'is_draft' => 'Estado del registro',
        'domicilio.calle' => 'Calle',
        'domicilio.numero_ext' => 'Numero exterior',
        'domicilio.numero_int' => 'Numero interior',
        'domicilio.colonia' => 'Colonia',
        'domicilio.municipio_id' => 'Municipio',
        'domicilio.codigo_postal' => 'Codigo postal',
        'domicilio.seccional' => 'Seccional del domicilio',
        'domicilio.distrito_local' => 'Distrito local',
        'domicilio.distrito_federal' => 'Distrito federal',
    ];
    $firstErrorKey = $errors->keys()[0] ?? null;
    $firstErrorLabel = $firstErrorKey
        ? ($fieldLabels[$firstErrorKey] ?? ucfirst(str_replace(['.', '_'], [' ', ' '], $firstErrorKey)))
        : null;
@endphp
@if ($errors->any())
    <div class="alert alert-danger"><strong>Revisa el formulario{{ $firstErrorLabel ? ' - Campo: ' . $firstErrorLabel : '' }}</strong></div>
@endif

@once
    <style>
        .wizard-step { display: none; }
        .wizard-step.active { display: block; }
        .wizard-progress { height: 6px; background-color: rgba(255, 255, 255, 0.15); border-radius: 999px; overflow: hidden; }
        .wizard-progress-bar { height: 100%; background: #0d6efd; transition: width 0.3s ease; }
        .wizard-step-label.active { color: #ffffff; font-weight: 600; }
    </style>
@endonce

<div id="beneficiarioWizard" class="beneficiario-wizard">
    <div class="mb-4">
        <div class="wizard-progress">
            <div class="wizard-progress-bar" id="wizardProgressBar" style="width:50%;"></div>
        </div>
        <div class="d-flex justify-content-between mt-2 small text-muted">
            <span class="wizard-step-label active" data-step-label="1">Datos personales</span>
            <span class="wizard-step-label" data-step-label="2">Domicilio</span>
        </div>
    </div>

    <div class="wizard-step active" data-step="1">
        <div class="row g-3">
            <input type="hidden" name="is_draft" value="{{ old('is_draft', 0) }}">
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
                <label class="form-label">Edad</label>
                <input type="number" name="edad" value="{{ old('edad', $b->edad ?? '') }}" class="form-control" readonly>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sexo</label>
                <select name="sexo" class="form-select @error('sexo') is-invalid @enderror">
                    <option value="">-</option>
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
    </div>

    <div class="wizard-step" data-step="2">
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
                    <option value="">-</option>
                    @foreach($municipios as $id=>$nombre)
                        <option value="{{ $id }}" @selected(old('domicilio.municipio_id', $domicilio->municipio_id ?? ($b->municipio_id ?? ''))==$id)>{{ $nombre }}</option>
                    @endforeach
                </select>
                @error('domicilio.municipio_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mt-4" id="wizardControls">
        <button type="button" class="btn btn-primary" data-wizard-next>Siguiente</button>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const wizard = document.getElementById('beneficiarioWizard');
    const progressBar = document.getElementById('wizardProgressBar');
    const stepLabels = wizard?.querySelectorAll('[data-step-label]') || [];
    const steps = wizard ? Array.from(wizard.querySelectorAll('.wizard-step')) : [];
    const prevBtn = wizard?.querySelector('[data-wizard-prev]');
    const nextBtn = wizard?.querySelector('[data-wizard-next]');
    const form = wizard?.closest('form');
    const submitBtn = form?.querySelector('button[type="submit"]');
    let currentStep = 0;

    const findStepWithErrors = () => steps.findIndex(step => step.querySelector('.is-invalid'));

    const updateStep = () => {
        steps.forEach((step, index) => step.classList.toggle('active', index === currentStep));
        stepLabels.forEach((label, index) => label.classList.toggle('active', index === currentStep));
        if (progressBar) {
            const percent = ((currentStep + 1) / Math.max(steps.length, 1)) * 100;
            progressBar.style.width = `${percent}%`;
        }
        if (prevBtn) {
            prevBtn.disabled = currentStep === 0;
        }
        if (nextBtn) {
            nextBtn.classList.toggle('d-none', currentStep === steps.length - 1);
        }
        if (submitBtn) {
            submitBtn.classList.toggle('d-none', currentStep !== steps.length - 1);
        }
    };

    const validateCurrentStep = () => {
        const step = steps[currentStep];
        if (!step) return true;
        const fields = Array.from(step.querySelectorAll('input, select, textarea')).filter(field => field.type !== 'hidden' && !field.closest('.d-none'));
        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
        return true;
    };

    if (submitBtn) {
        submitBtn.classList.add('d-none');
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (!validateCurrentStep()) return;
            currentStep = Math.min(currentStep + 1, steps.length - 1);
            updateStep();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            currentStep = Math.max(currentStep - 1, 0);
            updateStep();
        });
    }

    const errorStep = findStepWithErrors();
    if (errorStep >= 0) {
        currentStep = errorStep;
    }
    updateStep();

    const secc = document.getElementById('dom-seccional');
    if (window.beneficiarioWizardShouldReset && form) {
        form.reset();
        currentStep = 0;
        updateStep();
        const focusTarget = form.querySelector('input:not([type="hidden"]), select, textarea');
        try { focusTarget?.focus({ preventScroll: true }); } catch (_) { focusTarget?.focus(); }
        window.beneficiarioWizardShouldReset = false;
    }

    const munSel = document.getElementById('dom-municipio-id');
    if (secc) {
        const applyData = (data) => {
            if (!data) return;
            if (munSel && data.municipio_id) munSel.value = String(data.municipio_id);
        };
        const clearData = () => applyData({ municipio_id: '' });
        let timer = null;
        const debounced = (fn, wait = 400) => (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); };
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
    }
});
</script>
@endpush

