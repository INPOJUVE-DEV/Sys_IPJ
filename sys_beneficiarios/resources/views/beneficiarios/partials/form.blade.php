@php
    $b = $beneficiario ?? null;
    $domicilio = $domicilio ?? $b?->domicilio;
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
        'telefono' => 'Teléfono',
        'domicilio.calle' => 'Calle',
        'domicilio.numero_ext' => 'Numero exterior',
        'domicilio.numero_int' => 'Numero interior',
        'domicilio.colonia' => 'Colonia',
        'domicilio.municipio_id' => 'Municipio',
        'domicilio.codigo_postal' => 'Codigo postal',
        'domicilio.seccional' => 'Seccional del domicilio',
    ];
    $firstErrorKey = $errors->keys()[0] ?? null;
    $firstErrorLabel = $firstErrorKey
        ? ($fieldLabels[$firstErrorKey] ?? ucfirst(str_replace(['.', '_'], [' ', ' '], $firstErrorKey)))
        : null;
@endphp
@if ($errors->any())
    <div class="alert alert-danger"><strong>Revisa el
            formulario{{ $firstErrorLabel ? ' - Campo: ' . $firstErrorLabel : '' }}</strong></div>
@endif

@once
    <style>
        .wizard-step {
            display: none;
        }

        .wizard-step.active {
            display: block;
        }

        .wizard-progress {
            height: 6px;
            background-color: rgba(255, 255, 255, 0.15);
            border-radius: 999px;
            overflow: hidden;
        }

        .wizard-progress-bar {
            height: 100%;
            background: #0d6efd;
            transition: width 0.3s ease;
        }

        .wizard-step-label.active {
            color: #ffffff;
            font-weight: 600;
        }

        /* OCR INE styles */
        .ocr-scan-bar {
            background: linear-gradient(135deg, rgba(13, 110, 253, .12), rgba(13, 110, 253, .04));
            border: 1px dashed rgba(13, 110, 253, .35);
            border-radius: .5rem;
            padding: .75rem 1rem;
            margin-bottom: 1rem;
        }

        .ocr-preview-img {
            max-height: 120px;
            border-radius: .375rem;
            border: 1px solid rgba(255, 255, 255, .15);
            object-fit: cover;
        }

        .ocr-camera-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 10;
            border-radius: .5rem;
            overflow: hidden;
            background: #000;
            border: 1px solid rgba(255, 255, 255, .12);
        }

        .ocr-camera-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .ocr-camera-guide {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: min(88%, 560px);
            aspect-ratio: 1.585;
            border: 2px dashed rgba(13, 110, 253, .95);
            border-radius: .6rem;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, .32);
            pointer-events: none;
        }

        .ocr-camera-guide-label {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, calc(-50% - 56%));
            background: rgba(13, 110, 253, .9);
            color: #fff;
            font-size: .75rem;
            padding: .25rem .5rem;
            border-radius: .35rem;
            pointer-events: none;
            white-space: nowrap;
        }

        .ocr-confidence-high {
            color: #198754;
        }

        .ocr-confidence-med {
            color: #ffc107;
        }

        .ocr-confidence-low {
            color: #dc3545;
        }

        .ocr-field-warning {
            box-shadow: 0 0 0 2px rgba(255, 193, 7, .5);
        }
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
        {{-- OCR Escanear INE --}}
        @if(($mode ?? 'create') === 'create')
            <div class="ocr-scan-bar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <i class="bi bi-camera fs-5 text-primary me-1"></i>
                    <span class="fw-semibold">¿Tienes la INE a la mano?</span>
                    <span class="text-muted small ms-1">Escanea para pre-llenar el formulario.</span>
                </div>
                <button type="button" class="btn btn-primary btn-sm d-none" id="btnEscanearIne" data-bs-toggle="modal"
                    data-bs-target="#ocrIneModal">
                    <i class="bi bi-upc-scan me-1"></i>Escanear INE
                </button>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label for="folio_tarjeta" class="form-label">Folio tarjeta</label>
                <input id="folio_tarjeta" name="folio_tarjeta"
                    value="{{ old('folio_tarjeta', $b->folio_tarjeta ?? '') }}"
                    class="form-control @error('folio_tarjeta') is-invalid @enderror" required>
                @error('folio_tarjeta')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if(($mode ?? 'create') === 'create')
                <div class="col-md-4">
                    <label for="telefono" class="form-label">Teléfono (10 dígitos)</label>
                    <input id="telefono" name="telefono" value="{{ old('telefono', $b->telefono ?? '') }}"
                        class="form-control @error('telefono') is-invalid @enderror" required>
                    @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
            <div class="col-md-4">
                <label for="nombre" class="form-label">Nombre</label>
                <input id="nombre" name="nombre" value="{{ old('nombre', $b->nombre ?? '') }}"
                    class="form-control @error('nombre') is-invalid @enderror" required>
                @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="apellido_paterno" class="form-label">Apellido paterno</label>
                <input id="apellido_paterno" name="apellido_paterno"
                    value="{{ old('apellido_paterno', $b->apellido_paterno ?? '') }}"
                    class="form-control @error('apellido_paterno') is-invalid @enderror" required>
                @error('apellido_paterno')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="apellido_materno" class="form-label">Apellido materno</label>
                <input id="apellido_materno" name="apellido_materno"
                    value="{{ old('apellido_materno', $b->apellido_materno ?? '') }}"
                    class="form-control @error('apellido_materno') is-invalid @enderror" required>
                @error('apellido_materno')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="curp" class="form-label">CURP</label>
                <input id="curp" name="curp" value="{{ old('curp', $b->curp ?? '') }}" maxlength="18" minlength="18"
                    class="form-control text-uppercase @error('curp') is-invalid @enderror" required>
                @error('curp')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="fecha_nacimiento" class="form-label">Fecha nacimiento</label>
                <input id="fecha_nacimiento" type="date" name="fecha_nacimiento"
                    value="{{ old('fecha_nacimiento', isset($b) ? optional($b->fecha_nacimiento)->format('Y-m-d') : '') }}"
                    class="form-control @error('fecha_nacimiento') is-invalid @enderror" required>
                @error('fecha_nacimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="edad" class="form-label">Edad</label>
                <input id="edad" type="number" name="edad" value="{{ old('edad', $b->edad ?? '') }}"
                    class="form-control" readonly>
            </div>
            <div class="col-md-2">
                <label for="sexo" class="form-label">Sexo</label>
                <select id="sexo" name="sexo" class="form-select @error('sexo') is-invalid @enderror">
                    <option value="">-</option>
                    @foreach(['M' => 'M', 'F' => 'F', 'X' => 'X'] as $key => $val)
                        <option value="{{ $key }}" @selected(old('sexo', $b->sexo ?? '') == $key)>{{ $val }}</option>
                    @endforeach
                </select>
                @error('sexo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <div class="form-check mt-4">
                    <input type="hidden" name="discapacidad" value="0">
                    <input id="discapacidad" class="form-check-input" type="checkbox" name="discapacidad" value="1"
                        @checked(old('discapacidad', $b->discapacidad ?? false))>
                    <label for="discapacidad" class="form-check-label">Discapacidad</label>
                </div>
            </div>
            @if(($mode ?? 'create') !== 'create')
                <div class="col-md-3">
                    <label for="telefono" class="form-label">Teléfono (10 dígitos)</label>
                    <input id="telefono" name="telefono" value="{{ old('telefono', $b->telefono ?? '') }}"
                        class="form-control @error('telefono') is-invalid @enderror" required>
                    @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
        </div>
    </div>

    <div class="wizard-step" data-step="2">
        <h5>Domicilio</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="domicilio_calle" class="form-label">Calle</label>
                <input id="domicilio_calle" name="domicilio[calle]"
                    value="{{ old('domicilio.calle', $domicilio->calle ?? '') }}"
                    class="form-control @error('domicilio.calle') is-invalid @enderror">
                @error('domicilio.calle')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="domicilio_numero_ext" class="form-label">Número ext</label>
                <input id="domicilio_numero_ext" name="domicilio[numero_ext]"
                    value="{{ old('domicilio.numero_ext', $domicilio->numero_ext ?? '') }}"
                    class="form-control @error('domicilio.numero_ext') is-invalid @enderror">
                @error('domicilio.numero_ext')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="domicilio_numero_int" class="form-label">Número int</label>
                <input id="domicilio_numero_int" name="domicilio[numero_int]"
                    value="{{ old('domicilio.numero_int', $domicilio->numero_int ?? '') }}"
                    class="form-control @error('domicilio.numero_int') is-invalid @enderror">
                @error('domicilio.numero_int')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="domicilio_colonia" class="form-label">Colonia</label>
                <input id="domicilio_colonia" name="domicilio[colonia]"
                    value="{{ old('domicilio.colonia', $domicilio->colonia ?? '') }}"
                    class="form-control @error('domicilio.colonia') is-invalid @enderror">
                @error('domicilio.colonia')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="domicilio_codigo_postal" class="form-label">CP</label>
                <input id="domicilio_codigo_postal" name="domicilio[codigo_postal]"
                    value="{{ old('domicilio.codigo_postal', $domicilio->codigo_postal ?? '') }}"
                    class="form-control @error('domicilio.codigo_postal') is-invalid @enderror">
                @error('domicilio.codigo_postal')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="id_ine" class="form-label">ID INE</label>
                <input id="id_ine" name="id_ine" value="{{ old('id_ine', $b->id_ine ?? '') }}"
                    class="form-control @error('id_ine') is-invalid @enderror" required>
                <div class="form-text">Los primeros 4 dígitos se sincronizan con la seccional.</div>
                @error('id_ine')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="dom-seccional" class="form-label">Seccional</label>
                <input id="dom-seccional" name="domicilio[seccional]"
                    value="{{ old('domicilio.seccional', $domicilio?->seccion?->seccional ?? '') }}"
                    class="form-control @error('domicilio.seccional') is-invalid @enderror" placeholder="Ej. 0001">
                <div class="form-text">Al validar la seccional completamos municipio y distritos.</div>
                @error('domicilio.seccional')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="dom-municipio-id" class="form-label">Municipio (autocompletado)</label>
                <select id="dom-municipio-id" name="domicilio[municipio_id]"
                    class="form-select @error('domicilio.municipio_id') is-invalid @enderror">
                    <option value="">Selecciona o deja que el sistema lo asigne</option>
                    @foreach($municipios as $id => $nombre)
                        <option value="{{ $id }}" @selected(old('domicilio.municipio_id', $domicilio->municipio_id ?? ($b->municipio_id ?? '')) == $id)>{{ $nombre }}</option>
                    @endforeach
                </select>
                <div class="form-text">Se rellena de acuerdo a la seccional detectada.</div>
                @error('domicilio.municipio_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Distritos detectados</label>
                <div class="bg-dark border border-white border-opacity-25 rounded-3 p-3 h-100"
                    id="dom-seccional-summary">
                    <div class="small text-white-50">Municipio</div>
                    <div class="fw-semibold" id="dom-seccional-muni">-</div>
                    <div class="small text-white-50 mt-2">DL / DF</div>
                    <div class="fw-semibold" id="dom-seccional-distritos">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-4" id="wizardControls">
        <button type="button" class="btn btn-outline-secondary" data-wizard-prev>Anterior</button>
        <button type="button" class="btn btn-primary" data-wizard-next>Siguiente</button>
    </div>
</div>

{{-- OCR INE Modal --}}
@if(($mode ?? 'create') === 'create')
    <div class="modal fade" id="ocrIneModal" tabindex="-1" aria-labelledby="ocrIneModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="ocrIneModalLabel"><i class="bi bi-upc-scan me-2"></i>Escanear INE</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Se intentará abrir la cámara automáticamente. Alinea la INE en el
                        recuadro y captura <strong>frente</strong> + <strong>reverso</strong>. También puedes subir
                        archivos manualmente.</p>
                    <div class="border border-secondary rounded-3 p-3 mb-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <div class="small text-muted">Usa el recuadro como guía para mejorar la captura.</div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-light btn-sm" id="btnOcrSwitchCamera">
                                    <i class="bi bi-arrow-repeat me-1"></i>Cambiar cámara
                                </button>
                                <button type="button" class="btn btn-outline-light btn-sm d-none" id="btnOcrStartCamera">
                                    <i class="bi bi-camera-video me-1"></i>Abrir cámara
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnOcrStopCamera">
                                    <i class="bi bi-pause-circle me-1"></i>Pausar
                                </button>
                            </div>
                        </div>
                        <div class="ocr-camera-wrap">
                            <video id="ocrCameraVideo" class="ocr-camera-video" autoplay playsinline muted></video>
                            <div class="ocr-camera-guide" id="ocrCameraGuide"></div>
                            <div class="ocr-camera-guide-label">Alinea la INE dentro del recuadro</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-primary btn-sm" id="btnCaptureFront">
                                <i class="bi bi-camera me-1"></i>Capturar frente
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" id="btnCaptureBack">
                                <i class="bi bi-camera me-1"></i>Capturar reverso
                            </button>
                        </div>
                        <canvas id="ocrCaptureCanvas" class="d-none"></canvas>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="ocr_front_image" class="form-label fw-semibold">Frente de la INE (manual)</label>
                            <input type="file" id="ocr_front_image" accept="image/jpeg,image/png"
                                class="form-control form-control-sm">
                            <img id="ocr_front_preview" class="ocr-preview-img mt-2 d-none" alt="Preview frontal">
                        </div>
                        <div class="col-md-6">
                            <label for="ocr_back_image" class="form-label fw-semibold">Reverso de la INE (manual)</label>
                            <input type="file" id="ocr_back_image" accept="image/jpeg,image/png"
                                class="form-control form-control-sm">
                            <img id="ocr_back_preview" class="ocr-preview-img mt-2 d-none" alt="Preview reverso">
                        </div>
                    </div>
                    <div id="ocrResultArea" class="d-none">
                        <hr class="border-secondary">
                        <div id="ocrResultSummary"></div>
                    </div>
                    <div id="ocrErrorArea" class="d-none">
                        <div class="alert alert-danger mt-3 mb-0" id="ocrErrorMsg"></div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnOcrProcesar" disabled>
                        <span class="spinner-border spinner-border-sm d-none me-1" id="ocrSpinner" role="status"></span>
                        <i class="bi bi-cpu me-1" id="ocrIcon"></i>Procesar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
            const idIne = document.getElementById('id_ine');
            if (window.beneficiarioWizardShouldReset && form) {
                form.reset();
                currentStep = 0;
                updateStep();
                const focusTarget = form.querySelector('input:not([type="hidden"]), select, textarea');
                try { focusTarget?.focus({ preventScroll: true }); } catch (_) { focusTarget?.focus(); }
                window.beneficiarioWizardShouldReset = false;
            }

            const munSel = document.getElementById('dom-municipio-id');
            const seccCard = document.getElementById('dom-seccional-summary');
            const seccMunicipio = document.getElementById('dom-seccional-muni');
            const seccDistritos = document.getElementById('dom-seccional-distritos');
            if (secc) {
                const firstFourDigits = (value) => (value || '').replace(/\D/g, '').slice(0, 4);
                const syncSeccionalFromIdIne = () => {
                    const pref = firstFourDigits(idIne?.value);
                    if (pref.length < 4 || secc.value === pref) return;
                    secc.value = pref;
                    secc.dispatchEvent(new Event('input', { bubbles: true }));
                };
                const syncIdIneFromSeccional = () => {
                    if (!idIne) return;
                    const pref = firstFourDigits(secc.value);
                    if (pref.length < 4) return;
                    const current = (idIne.value || '').trim();
                    if (!current) {
                        idIne.value = pref;
                        idIne.dispatchEvent(new Event('input', { bubbles: true }));
                        return;
                    }
                    if (current.slice(0, 4) !== pref) {
                        idIne.value = `${pref}${current.slice(4)}`;
                        idIne.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                };
                const renderSummary = (municipio = '-', dl = '-', df = '-') => {
                    if (seccMunicipio) seccMunicipio.textContent = municipio || '-';
                    if (seccDistritos) seccDistritos.textContent = `DL: ${dl || '--'} · DF: ${df || '--'}`;
                };
                const toggleSummaryState = (hasData) => {
                    seccCard?.classList.toggle('border-success', !!hasData);
                    seccCard?.classList.toggle('border-white', !hasData);
                };
                const applyData = (data) => {
                    if (!data) return;
                    if (munSel) munSel.value = data.municipio_id ? String(data.municipio_id) : '';
                    renderSummary(data.municipio || '-', data.distrito_local || '-', data.distrito_federal || '-');
                    toggleSummaryState(true);
                };
                const clearData = () => {
                    if (munSel) munSel.value = '';
                    renderSummary('-', '-', '-');
                    toggleSummaryState(false);
                };
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
                idIne?.addEventListener('input', syncSeccionalFromIdIne);
                secc.addEventListener('input', (e) => debouncedFetch(e.target.value));
                secc.addEventListener('change', (e) => {
                    syncIdIneFromSeccional();
                    fetchDistritos(e.target.value);
                });
                secc.addEventListener('blur', (e) => {
                    syncIdIneFromSeccional();
                    fetchDistritos(e.target.value);
                });
                if (idIne?.value) {
                    syncSeccionalFromIdIne();
                }
                if (secc.value) {
                    fetchDistritos(secc.value);
                } else {
                    clearData();
                }
            }
        });
    </script>

    {{-- OCR INE Script --}}
    @if(($mode ?? 'create') === 'create')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('ocrIneModal');
                const frontInput = document.getElementById('ocr_front_image');
                const backInput = document.getElementById('ocr_back_image');
                const frontPrev = document.getElementById('ocr_front_preview');
                const backPrev = document.getElementById('ocr_back_preview');
                const btnProcesar = document.getElementById('btnOcrProcesar');
                const spinner = document.getElementById('ocrSpinner');
                const ocrIcon = document.getElementById('ocrIcon');
                const resultArea = document.getElementById('ocrResultArea');
                const resultSummary = document.getElementById('ocrResultSummary');
                const errorArea = document.getElementById('ocrErrorArea');
                const errorMsg = document.getElementById('ocrErrorMsg');
                const cameraVideo = document.getElementById('ocrCameraVideo');
                const cameraGuide = document.getElementById('ocrCameraGuide');
                const captureCanvas = document.getElementById('ocrCaptureCanvas');
                const btnStartCamera = document.getElementById('btnOcrStartCamera');
                const btnStopCamera = document.getElementById('btnOcrStopCamera');
                const btnSwitchCamera = document.getElementById('btnOcrSwitchCamera');
                const btnCaptureFront = document.getElementById('btnCaptureFront');
                const btnCaptureBack = document.getElementById('btnCaptureBack');

                const canUseCamera = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && cameraVideo);
                let cameraStream = null;
                let preferredFacingMode = 'environment';
                const cameraCaptures = { front: null, back: null };

                const beneficiarioFields = {
                    'nombre': 'nombre',
                    'apellido_paterno': 'apellido_paterno',
                    'apellido_materno': 'apellido_materno',
                    'curp': 'curp',
                    'fecha_nacimiento': 'fecha_nacimiento',
                    'sexo': 'sexo',
                    'id_ine': 'id_ine',
                };

                const domicilioFields = {
                    'calle': 'domicilio_calle',
                    'colonia': 'domicilio_colonia',
                    'codigo_postal': 'domicilio_codigo_postal',
                    'seccional': 'dom-seccional',
                };

                const confidenceClass = (c) => c >= 0.85 ? 'ocr-confidence-high' : c >= 0.65 ? 'ocr-confidence-med' : 'ocr-confidence-low';
                const confidenceTag = (c) => c >= 0.85 ? 'ALTA' : c >= 0.65 ? 'MEDIA' : 'BAJA';

                const showError = (message) => {
                    if (!errorMsg || !errorArea) return;
                    errorMsg.textContent = message;
                    errorArea.classList.remove('d-none');
                };

                const hideError = () => {
                    errorArea?.classList.add('d-none');
                };

                const setPreview = (img, file) => {
                    if (!img) return;
                    if (!file) {
                        img.classList.add('d-none');
                        img.removeAttribute('src');
                        return;
                    }
                    img.src = URL.createObjectURL(file);
                    img.classList.remove('d-none');
                };

                const setInputFile = (input, file) => {
                    if (!input || !file || typeof DataTransfer === 'undefined') return false;
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    return true;
                };

                const getSelectedFile = (side) => {
                    if (side === 'front') {
                        return frontInput?.files?.[0] || cameraCaptures.front;
                    }
                    return backInput?.files?.[0] || cameraCaptures.back;
                };

                const checkReady = () => {
                    if (btnProcesar) btnProcesar.disabled = !(getSelectedFile('front') && getSelectedFile('back'));
                };

                const updateCameraButtons = () => {
                    const cameraOn = !!cameraStream;
                    btnStartCamera?.classList.toggle('d-none', cameraOn);
                    btnStopCamera?.classList.toggle('d-none', !cameraOn);
                    if (btnCaptureFront) btnCaptureFront.disabled = !cameraOn;
                    if (btnCaptureBack) btnCaptureBack.disabled = !cameraOn;
                    if (btnSwitchCamera) btnSwitchCamera.disabled = !cameraOn;
                };

                const bindUploadPreview = (input, preview, side) => {
                    input?.addEventListener('change', () => {
                        const file = input.files[0] || null;
                        cameraCaptures[side] = file;
                        setPreview(preview, file);
                        checkReady();
                    });
                };

                bindUploadPreview(frontInput, frontPrev, 'front');
                bindUploadPreview(backInput, backPrev, 'back');

                const saveCapture = (side, file) => {
                    cameraCaptures[side] = file;
                    const isFront = side === 'front';
                    const input = isFront ? frontInput : backInput;
                    const preview = isFront ? frontPrev : backPrev;
                    const assigned = setInputFile(input, file);
                    if (!assigned) {
                        setPreview(preview, file);
                    }
                    checkReady();
                };

                const stopCamera = () => {
                    if (cameraStream) {
                        cameraStream.getTracks().forEach((track) => track.stop());
                    }
                    cameraStream = null;
                    if (cameraVideo) {
                        cameraVideo.pause();
                        cameraVideo.srcObject = null;
                    }
                    updateCameraButtons();
                };

                const startCamera = async () => {
                    if (!canUseCamera) {
                        showError('Este dispositivo o navegador no permite acceso a cámara. Usa carga manual.');
                        return;
                    }

                    hideError();
                    stopCamera();

                    const preferredConstraints = {
                        video: {
                            facingMode: { ideal: preferredFacingMode },
                            width: { ideal: 1920 },
                            height: { ideal: 1080 },
                        },
                        audio: false,
                    };

                    try {
                        cameraStream = await navigator.mediaDevices.getUserMedia(preferredConstraints);
                    } catch (_) {
                        try {
                            cameraStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                        } catch (_) {
                            showError('No se pudo abrir la cámara. Revisa permisos o usa carga manual.');
                            stopCamera();
                            return;
                        }
                    }

                    cameraVideo.srcObject = cameraStream;
                    try {
                        await cameraVideo.play();
                    } catch (_) {
                        // ignore play rejection on some browsers
                    }
                    updateCameraButtons();
                };

                const clamp = (v, min, max) => Math.min(max, Math.max(min, v));

                const getCropCoordinates = () => {
                    if (!cameraVideo || !cameraGuide || !cameraVideo.videoWidth || !cameraVideo.videoHeight) return null;
                    const videoRect = cameraVideo.getBoundingClientRect();
                    const guideRect = cameraGuide.getBoundingClientRect();
                    const displayW = videoRect.width;
                    const displayH = videoRect.height;
                    if (!displayW || !displayH) return null;

                    const sourceW = cameraVideo.videoWidth;
                    const sourceH = cameraVideo.videoHeight;
                    const scale = Math.max(displayW / sourceW, displayH / sourceH);
                    const renderedW = sourceW * scale;
                    const renderedH = sourceH * scale;
                    const offsetX = (displayW - renderedW) / 2;
                    const offsetY = (displayH - renderedH) / 2;

                    const gx = guideRect.left - videoRect.left;
                    const gy = guideRect.top - videoRect.top;
                    const gw = guideRect.width;
                    const gh = guideRect.height;

                    let sx = (gx - offsetX) / scale;
                    let sy = (gy - offsetY) / scale;
                    let sw = gw / scale;
                    let sh = gh / scale;

                    sx = Math.round(clamp(sx, 0, sourceW - 1));
                    sy = Math.round(clamp(sy, 0, sourceH - 1));
                    sw = Math.round(clamp(sw, 1, sourceW - sx));
                    sh = Math.round(clamp(sh, 1, sourceH - sy));

                    return { sx, sy, sw, sh };
                };

                const captureSide = async (side) => {
                    if (!cameraStream) {
                        await startCamera();
                        if (!cameraStream) return;
                    }

                    const crop = getCropCoordinates();
                    if (!crop || !captureCanvas) {
                        showError('No fue posible capturar desde la cámara. Intenta de nuevo.');
                        return;
                    }

                    captureCanvas.width = crop.sw;
                    captureCanvas.height = crop.sh;

                    const ctx = captureCanvas.getContext('2d');
                    if (!ctx) {
                        showError('No se pudo preparar la captura de imagen.');
                        return;
                    }

                    ctx.drawImage(cameraVideo, crop.sx, crop.sy, crop.sw, crop.sh, 0, 0, crop.sw, crop.sh);

                    const blob = await new Promise((resolve) => captureCanvas.toBlob(resolve, 'image/jpeg', 0.92));
                    if (!blob) {
                        showError('No se pudo generar la captura.');
                        return;
                    }

                    const file = new File([blob], `ine_${side}_${Date.now()}.jpg`, { type: 'image/jpeg' });
                    saveCapture(side, file);
                    hideError();
                };

                btnCaptureFront?.addEventListener('click', () => captureSide('front'));
                btnCaptureBack?.addEventListener('click', () => captureSide('back'));
                btnStartCamera?.addEventListener('click', () => startCamera());
                btnStopCamera?.addEventListener('click', () => stopCamera());
                btnSwitchCamera?.addEventListener('click', async () => {
                    preferredFacingMode = preferredFacingMode === 'environment' ? 'user' : 'environment';
                    await startCamera();
                });

                modalEl?.addEventListener('shown.bs.modal', () => {
                    startCamera();
                    checkReady();
                });

                modalEl?.addEventListener('hidden.bs.modal', () => {
                    stopCamera();
                    hideError();
                });

                const setFieldValue = (id, value) => {
                    const el = document.getElementById(id);
                    if (!el || !value) return;
                    if (el.tagName === 'SELECT') {
                        const opt = Array.from(el.options).find(o => o.value === value);
                        if (opt) el.value = value;
                    } else {
                        el.value = value;
                    }
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                };

                const highlightField = (id, confidence) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.classList.remove('ocr-field-warning');
                    if (confidence < 0.65) {
                        el.classList.add('ocr-field-warning');
                    }
                };

                const fillFormFromOcr = (data) => {
                    let filledCount = 0;
                    let lowFields = [];
                    const summaryItems = [];

                    if (data.beneficiarios) {
                        for (const [key, inputId] of Object.entries(beneficiarioFields)) {
                            const field = data.beneficiarios[key];
                            if (field && field.value) {
                                setFieldValue(inputId, field.value);
                                highlightField(inputId, field.confidence || 0);
                                filledCount++;
                                summaryItems.push(`<span class="${confidenceClass(field.confidence || 0)}">${confidenceTag(field.confidence || 0)} ${key}</span>`);
                                if ((field.confidence || 0) < 0.65) lowFields.push(key);
                            }
                        }
                    }

                    if (data.domicilio) {
                        for (const [key, inputId] of Object.entries(domicilioFields)) {
                            const field = data.domicilio[key];
                            if (field && field.value) {
                                setFieldValue(inputId, field.value);
                                highlightField(inputId, field.confidence || 0);
                                filledCount++;
                                summaryItems.push(`<span class="${confidenceClass(field.confidence || 0)}">${confidenceTag(field.confidence || 0)} ${key}</span>`);
                                if ((field.confidence || 0) < 0.65) lowFields.push(key);
                            }
                        }
                    }

                    const curpInput = document.getElementById('curp');
                    if (curpInput && curpInput.value.length === 18) {
                        curpInput.dispatchEvent(new Event('blur', { bubbles: true }));
                    }

                    const seccInput = document.getElementById('dom-seccional');
                    if (seccInput && seccInput.value) {
                        setTimeout(() => {
                            seccInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }, 300);
                    }

                    return { filledCount, lowFields, summaryItems };
                };

                btnProcesar?.addEventListener('click', async () => {
                    const frontFile = getSelectedFile('front');
                    const backFile = getSelectedFile('back');
                    if (!frontFile || !backFile) {
                        showError('Captura o selecciona frente y reverso de la INE antes de procesar.');
                        return;
                    }

                    btnProcesar.disabled = true;
                    spinner.classList.remove('d-none');
                    ocrIcon.classList.add('d-none');
                    resultArea.classList.add('d-none');
                    hideError();

                    const formData = new FormData();
                    formData.append('front_image', frontFile);
                    formData.append('back_image', backFile);

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                        const res = await fetch('/api/ocr/ine/extract', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: formData,
                        });

                        if (!res.ok) {
                            const err = await res.json().catch(() => ({}));
                            let msg = 'Error al procesar la INE.';
                            if (res.status === 422) msg = err.message || 'Imágenes inválidas. Verifica formato y tamaño (máx 5MB).';
                            else if (res.status === 429) msg = 'Demasiadas solicitudes. Espera un momento e intenta de nuevo.';
                            else if (res.status === 502 || res.status === 504) msg = 'El servicio OCR no está disponible. Intenta más tarde.';
                            else if (err.message) msg = err.message;
                            throw new Error(msg);
                        }

                        const data = await res.json();
                        const { filledCount, lowFields, summaryItems } = fillFormFromOcr(data);

                        let html = `<p class="mb-2 fw-semibold"><i class="bi bi-check-circle text-success me-1"></i>Se pre-llenaron <strong>${filledCount}</strong> campos:</p>`;
                        html += `<div class="d-flex flex-wrap gap-2 small">${summaryItems.join('')}</div>`;
                        if (lowFields.length) {
                            html += `<div class="alert alert-warning mt-2 mb-0 small py-2"><i class="bi bi-exclamation-triangle me-1"></i>Revisa los campos marcados en amarillo: <strong>${lowFields.join(', ')}</strong></div>`;
                        }
                        if (data.warnings && data.warnings.length) {
                            html += `<div class="alert alert-info mt-2 mb-0 small py-2"><i class="bi bi-info-circle me-1"></i>${data.warnings.join(', ')}</div>`;
                        }
                        resultSummary.innerHTML = html;
                        resultArea.classList.remove('d-none');

                        if (lowFields.length === 0) {
                            setTimeout(() => {
                                const modal = bootstrap.Modal.getInstance(document.getElementById('ocrIneModal'));
                                modal?.hide();
                            }, 1500);
                        }
                    } catch (err) {
                        showError(err.message || 'Error desconocido al procesar OCR.');
                    } finally {
                        btnProcesar.disabled = false;
                        spinner.classList.add('d-none');
                        ocrIcon.classList.remove('d-none');
                        checkReady();
                    }
                });

                updateCameraButtons();
                checkReady();
            });
        </script>
    @endif
@endpush
