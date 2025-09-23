<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="h4 m-0">Asignar beneficiario</h1>
                <div class="text-muted small">Grupo: {{ $group->name }} ({{ $group->code }})</div>
            </div>
            <a href="{{ route('vol.groups.show', $group) }}" class="btn btn-outline-light">Volver al grupo</a>
        </div>
    </x-slot>

    <div class="card mb-4">
        <div class="card-body row g-3 small">
            <div class="col-md-3">
                <span class="text-muted">Tipo</span>
                <div class="fw-semibold">{{ $group->type }}</div>
            </div>
            <div class="col-md-3">
                <span class="text-muted">Horario</span>
                <div class="fw-semibold text-uppercase">{{ $group->schedule_template }}</div>
            </div>
            <div class="col-md-3">
                <span class="text-muted">Capacidad</span>
                <div class="fw-semibold">{{ $group->capacity }}</div>
            </div>
            <div class="col-md-3">
                <span class="text-muted">Inscritos activos</span>
                <div class="fw-semibold">{{ $group->active_enrollments ?? $activeEnrollments ?? 0 }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <form id="enrollment-form" action="{{ route('vol.enrollments.store', $group) }}" method="POST" data-lookup-url="{{ $lookupUrl ?? '' }}" data-validate-url="{{ $validationUrl ?? '' }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">CURP del beneficiario</label>
                            <div class="input-group">
                                <input type="text" id="assign-curp" class="form-control" placeholder="CURP" maxlength="18">
                                <button type="button" class="btn btn-outline-light" id="assign-lookup">Buscar</button>
                            </div>
                            <div id="assign-result" class="form-text text-warning"></div>
                            <input type="hidden" name="beneficiario_id" id="assign-beneficiario-id" value="{{ old('beneficiario_id') }}">
                        </div>

                        <div id="validation-alerts" class="mb-3 d-none"></div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Inscribir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6">Instrucciones</h2>
                    <ol class="small mb-0">
                        <li>Busca al beneficiario por CURP y confirma la coincidencia.</li>
                        <li>Verifica que existan pagos vigentes y cupo disponible.</li>
                        <li>Si todo esta correcto, presiona "Inscribir" para registrar la participacion.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
(function(){
    const form = document.getElementById('enrollment-form');
    if (!form) return;
    const lookupBtn = document.getElementById('assign-lookup');
    const curpInput = document.getElementById('assign-curp');
    const hiddenId = document.getElementById('assign-beneficiario-id');
    const resultBox = document.getElementById('assign-result');
    const alertsBox = document.getElementById('validation-alerts');
    const lookupUrl = form.dataset.lookupUrl || '';
    const validateUrl = form.dataset.validateUrl || '';

    function setResult(message, type = 'muted') {
        if (!resultBox) return;
        resultBox.classList.remove('text-muted','text-success','text-danger');
        resultBox.classList.add('text-' + type);
        resultBox.textContent = message;
    }

    function showAlerts(messages) {
        if (!alertsBox) return;
        if (!messages || messages.length === 0) {
            alertsBox.classList.add('d-none');
            alertsBox.innerHTML = '';
            return;
        }
        const items = messages.map(msg => `<div class="alert alert-${msg.type}">${msg.text}</div>`).join('');
        alertsBox.innerHTML = items;
        alertsBox.classList.remove('d-none');
    }

    async function runValidation(beneficiarioId) {
        if (!validateUrl) {
            showAlerts([]);
            return;
        }
        try {
            let url = validateUrl;
            if (url.includes('{id}')) {
                url = url.replace('{id}', encodeURIComponent(beneficiarioId));
            } else if (url.includes('{beneficiario}')) {
                url = url.replace('{beneficiario}', encodeURIComponent(beneficiarioId));
            } else {
                url += (url.includes('?') ? '&' : '?') + 'beneficiario_id=' + encodeURIComponent(beneficiarioId);
            }
            const response = await fetch(url, { headers: { 'Accept': 'application/json' }});
            if (!response.ok) throw new Error('validacion');
            const payload = await response.json();
            const alerts = [];
            if (payload.has_payment === false) alerts.push({ type: 'danger', text: 'No se detecto pago previo para el beneficiario.' });
            if (payload.monthly_duplicate === true) alerts.push({ type: 'warning', text: 'El beneficiario ya tiene una inscripcion en este mes.' });
            if (payload.has_capacity === false) alerts.push({ type: 'danger', text: 'El grupo no tiene cupo disponible.' });
            if (alerts.length === 0) alerts.push({ type: 'success', text: 'Validaciones completadas. Puedes continuar con la inscripcion.' });
            showAlerts(alerts);
        } catch (error) {
            showAlerts([{ type: 'danger', text: 'No fue posible validar las reglas del grupo.' }]);
        }
    }

    if (lookupBtn) {
        lookupBtn.addEventListener('click', async () => {
            const curp = (curpInput.value || '').trim().toUpperCase();
            hiddenId.value = '';
            showAlerts([]);
            if (!curp) {
                setResult('Capture una CURP para buscar.', 'danger');
                return;
            }
            if (!lookupUrl) {
                setResult('No se configuro la ruta de busqueda.', 'danger');
                return;
            }
            setResult('Buscando...', 'muted');
            try {
                const url = lookupUrl.includes('?') ? `${lookupUrl}&curp=${encodeURIComponent(curp)}` : `${lookupUrl}?curp=${encodeURIComponent(curp)}`;
                const response = await fetch(url, { headers: { 'Accept': 'application/json' }});
                if (!response.ok) throw new Error('Busqueda sin resultados');
                const payload = await response.json();
                if (!payload || !payload.id) throw new Error('No se encontro el beneficiario');
                hiddenId.value = payload.id;
                setResult(`Beneficiario: ${payload.nombre ?? payload.name ?? curp}`, 'success');
                runValidation(payload.id);
            } catch (error) {
                setResult(error.message || 'No se encontro el beneficiario.', 'danger');
            }
        });
    }
})();
</script>
@endpush