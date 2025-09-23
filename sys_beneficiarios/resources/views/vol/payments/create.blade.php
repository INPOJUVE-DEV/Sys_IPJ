<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1 class="h4 m-0">Registrar pago</h1>
            <a href="{{ route('vol.groups.index') }}" class="btn btn-outline-light">Volver a grupos</a>
        </div>
    </x-slot>

    <div class="card">
        <div class="card-body">
            <form id="payment-form" action="{{ route('vol.payments.store') }}" method="POST" class="row g-3" data-lookup-url="{{ $lookupUrl ?? '' }}">
                @csrf

                <div class="col-md-6">
                    <label class="form-label">CURP del beneficiario</label>
                    <div class="input-group">
                        <input type="text" id="beneficiario-curp" class="form-control" placeholder="CURP" maxlength="18">
                        <button type="button" class="btn btn-outline-light" id="btn-lookup">Buscar</button>
                    </div>
                    <input type="hidden" name="beneficiario_id" id="beneficiario-id" value="{{ old('beneficiario_id') }}">
                    <div id="lookup-result" class="form-text text-warning"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tipo de pago</label>
                    <select name="payment_type" class="form-select" required>
                        <option value="">Seleccione</option>
                        @foreach(['transferencia', 'tarjeta', 'deposito'] as $type)
                            <option value="{{ $type }}" @selected(old('payment_type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha de pago</label>
                    <input type="date" name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Referencia</label>
                    <input type="text" name="receipt_ref" value="{{ old('receipt_ref') }}" class="form-control" placeholder="Referencia opcional">
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-success">Guardar pago</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
(function(){
    const form = document.getElementById('payment-form');
    if (!form) return;
    const lookupBtn = document.getElementById('btn-lookup');
    const curpInput = document.getElementById('beneficiario-curp');
    const resultBox = document.getElementById('lookup-result');
    const hiddenId = document.getElementById('beneficiario-id');
    const lookupUrl = form.dataset.lookupUrl || '';

    function setResult(message, type = 'muted') {
        if (!resultBox) return;
        resultBox.classList.remove('text-muted','text-success','text-danger');
        resultBox.classList.add('text-' + type);
        resultBox.textContent = message;
    }

    if (lookupBtn) {
        lookupBtn.addEventListener('click', async () => {
            const curp = (curpInput.value || '').trim().toUpperCase();
            hiddenId.value = '';
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
                if (!payload || !payload.id) {
                    throw new Error('No se encontro el beneficiario');
                }
                hiddenId.value = payload.id;
                setResult(`Beneficiario: ${payload.nombre ?? payload.name ?? curp}`, 'success');
            } catch (error) {
                setResult(error.message || 'No se encontro el beneficiario.', 'danger');
            }
        });
    }
})();
</script>
@endpush