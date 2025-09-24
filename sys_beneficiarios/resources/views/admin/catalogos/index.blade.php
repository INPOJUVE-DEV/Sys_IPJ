<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 m-0">Importar catálogos</h2>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if(session('import_log'))
        <div class="alert alert-secondary"><pre class="mb-0 small">{{ session('import_log') }}</pre></div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('admin.catalogos.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Municipios CSV</label>
                        <input type="file" id="csvMunicipios" name="municipios" accept=".csv,text/csv" data-expected="clave,nombre" class="form-control @error('municipios') is-invalid @enderror">
                        <div class="form-text">Encabezados: clave,nombre</div>
                        <div class="text-danger small d-none" data-feedback="municipios"></div>
                        @error('municipios')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Secciones CSV</label>
                        <input type="file" id="csvSecciones" name="secciones" accept=".csv,text/csv" data-expected="seccional,municipio_clave,distrito_local,distrito_federal" class="form-control @error('secciones') is-invalid @enderror">
                        <div class="form-text">Encabezados: seccional,municipio_clave,distrito_local,distrito_federal</div>
                        <div class="text-danger small d-none" data-feedback="secciones"></div>
                        @error('secciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">SQL opcional</label>
                        <input type="file" name="sql" accept=".sql,.txt" class="form-control @error('sql') is-invalid @enderror">
                        @error('sql')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fresh" value="1" id="fresh">
                            <label class="form-check-label" for="fresh">Limpiar tablas antes de importar</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button class="btn btn-primary" type="submit">Ejecutar importación</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[action="{{ route('admin.catalogos.import') }}"]');
    if (!form) return;
    const fileInputs = Array.from(form.querySelectorAll('input[type="file"][data-expected]'));

    const feedbackFor = (input) => form.querySelector(`[data-feedback="${input.name}"]`);

    function setFeedback(input, message, state) {
        const feedback = feedbackFor(input);
        if (feedback) {
            feedback.textContent = message || '';
            feedback.classList.toggle('d-none', !message);
        }
        input.classList.remove('is-valid', 'is-invalid');
        if (state === 'valid') {
            input.classList.add('is-valid');
        } else if (state === 'invalid') {
            input.classList.add('is-invalid');
        }
        input.dataset.validationState = state || '';
    }

    function parseColumns(rawHeader) {
        return rawHeader
            .split(',')
            .map(col => col.trim().replace(/^"|"$/g, '').toLowerCase())
            .filter(Boolean);
    }

    function validateFile(input) {
        const expectedRaw = (input.dataset.expected || '').split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
        const file = input.files?.[0];
        if (!file) {
            setFeedback(input, '', '');
            return;
        }

        const isCsv = file.type === 'text/csv' || file.name.toLowerCase().endsWith('.csv') || file.type === 'application/vnd.ms-excel';
        if (!isCsv) {
            setFeedback(input, 'Selecciona un archivo CSV válido (.csv).', 'invalid');
            return;
        }

        input.dataset.validationState = 'pending';
        const reader = new FileReader();
        reader.onload = (event) => {
            const text = (event.target?.result || '').toString();
            const headerLine = text.split(/\r?\n/)[0] || '';
            if (!headerLine) {
                setFeedback(input, 'No fue posible leer encabezados del archivo.', 'invalid');
                return;
            }
            const columns = parseColumns(headerLine);
            const missing = expectedRaw.filter(col => !columns.includes(col));
            if (missing.length) {
                setFeedback(input, `Encabezados faltantes: ${missing.join(', ')}.`, 'invalid');
                return;
            }
            setFeedback(input, '', 'valid');
        };
        reader.onerror = () => {
            setFeedback(input, 'No fue posible leer el archivo. Intenta nuevamente.', 'invalid');
        };
        reader.readAsText(file.slice(0, 4096));
    }

    fileInputs.forEach(input => {
        input.addEventListener('change', () => validateFile(input));
    });

    form.addEventListener('submit', (event) => {
        let hasIssue = false;
        fileInputs.forEach(input => {
            if (!input.files?.length) return;
            if (!input.dataset.validationState || input.dataset.validationState === 'pending') {
                validateFile(input);
            }
            if (input.dataset.validationState !== 'valid') {
                hasIssue = true;
            }
        });
        if (hasIssue) {
            event.preventDefault();
            showToast('Revisa los archivos CSV antes de continuar.', 'danger');
        }
    });
});
</script>
@endpush

