<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1 class="h4 m-0">Registrar pago</h1>
            <a href="{{ route('vol.groups.index') }}" class="btn btn-outline-light">Volver a grupos</a>
        </div>
    </x-slot>

    <div class="card">
        <div class="card-body">
            <form id="payment-form" action="{{ route('vol.payments.store') }}" method="POST" class="row g-3 needs-validation" data-lookup-url="{{ $lookupUrl ?? '' }}" novalidate>
                @csrf

                <div class="col-md-6">
                    <label class="form-label">CURP del beneficiario</label>
                    <div class="input-group">
                        <input type="text" id="beneficiario-curp" class="form-control" placeholder="CURP" maxlength="18">
                        <button type="button" class="btn btn-outline-light" id="btn-lookup">Buscar</button>
                    </div>
                    <input type="hidden" name="beneficiario_id" id="beneficiario-id" value="{{ old('beneficiario_id') }}">
                    <div id="lookup-result" class="form-text text-warning"></div>
                    <div class="invalid-feedback" data-feedback-for="beneficiario-curp"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tipo de pago</label>
                    <select name="payment_type" class="form-select" required>
                        <option value="">Seleccione</option>
                        @foreach(['transferencia', 'tarjeta', 'deposito'] as $type)
                            <option value="{{ $type }}" @selected(old('payment_type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" data-feedback-for="payment_type"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha de pago</label>
                    <input type="date" name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" class="form-control" required>
                    <div class="invalid-feedback" data-feedback-for="payment_date"></div>
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
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('payment-form');
            if (!form) return;

            const lookupBtn = document.getElementById('btn-lookup');
            const curpInput = document.getElementById('beneficiario-curp');
            const resultBox = document.getElementById('lookup-result');
            const hiddenId = document.getElementById('beneficiario-id');
            const lookupUrl = form.dataset.lookupUrl || '';

            const escapeSelector = value => {
                if (window.CSS && typeof window.CSS.escape === 'function') {
                    return window.CSS.escape(value);
                }
                return value.replace(/([ #;?%&,.+*~':"!^$\[\]()=>|\/])/g, '\\$1');
            };

            const rules = [
                {
                    name: 'beneficiario-curp',
                    element: curpInput,
                    validate: () => {
                        const curp = (curpInput?.value || '').trim().toUpperCase();
                        if (curp.length === 0) {
                            return 'Capture la CURP para buscar al beneficiario.';
                        }
                        if (curp.length !== 18) {
                            return 'La CURP debe tener 18 caracteres.';
                        }
                        if (!hiddenId?.value) {
                            return 'Busque y seleccione un beneficiario válido.';
                        }
                        return true;
                    },
                    events: ['input', 'blur'],
                },
                {
                    name: 'payment_type',
                    validate: value => value !== '',
                    message: 'Seleccione el tipo de pago.',
                    events: ['change'],
                },
                {
                    name: 'payment_date',
                    validate: value => {
                        if (!value) {
                            return 'Indique la fecha del pago.';
                        }
                        const paymentDate = new Date(value + 'T00:00:00');
                        if (Number.isNaN(paymentDate.getTime())) {
                            return 'Capture una fecha válida.';
                        }
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        if (paymentDate > today) {
                            return 'La fecha de pago no puede ser futura.';
                        }
                        return true;
                    },
                    events: ['change', 'input'],
                },
            ];

            const setFeedback = (fieldName, message) => {
                const selector = `.invalid-feedback[data-feedback-for="${escapeSelector(fieldName)}"]`;
                const feedback = form.querySelector(selector);
                if (feedback) {
                    feedback.textContent = message;
                    feedback.style.display = message ? 'block' : '';
                }
            };

            const validateField = rule => {
                const input = rule.element ?? form.elements.namedItem(rule.name);
                if (!input) return true;

                const value = rule.element ? input.value : (input.value || '').trim();
                const result = typeof rule.validate === 'function'
                    ? rule.validate(value)
                    : (value !== '' ? true : (rule.message ?? 'Campo requerido'));
                const message = result === true ? '' : (typeof result === 'string' ? result : (rule.message ?? 'Campo requerido'));

                if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement) {
                    input.classList.toggle('is-invalid', !!message);
                    if (!message && value !== '') {
                        input.classList.add('is-valid');
                    } else {
                        input.classList.remove('is-valid');
                    }
                    if (typeof input.setCustomValidity === 'function') {
                        input.setCustomValidity(message);
                    }
                }

                setFeedback(rule.name, message);

                if (rule.name === 'beneficiario-curp' && resultBox) {
                    resultBox.classList.remove('text-danger', 'text-success', 'text-warning');
                    if (message) {
                        resultBox.textContent = message;
                        resultBox.classList.add('text-danger');
                    }
                }

                return !message;
            };

            rules.forEach(rule => {
                const input = rule.element ?? form.elements.namedItem(rule.name);
                if (!input) return;
                (rule.events || ['input']).forEach(eventName => {
                    input.addEventListener(eventName, () => validateField(rule));
                });
                validateField(rule);
            });

            const setResult = (message, type = 'muted') => {
                if (!resultBox) return;
                resultBox.classList.remove('text-muted', 'text-success', 'text-danger', 'text-warning');
                resultBox.classList.add('text-' + type);
                resultBox.textContent = message;
            };

            if (lookupBtn) {
                lookupBtn.addEventListener('click', async () => {
                    const curp = (curpInput.value || '').trim().toUpperCase();
                    hiddenId.value = '';

                    if (!curp) {
                        setResult('Capture una CURP para buscar.', 'danger');
                        validateField(rules[0]);
                        return;
                    }

                    if (curp.length !== 18) {
                        setResult('Verifique que la CURP tenga 18 caracteres.', 'danger');
                        validateField(rules[0]);
                        return;
                    }

                    if (!lookupUrl) {
                        setResult('No se configuró la ruta de búsqueda.', 'danger');
                        return;
                    }

                    setResult('Buscando...', 'muted');

                    try {
                        const url = lookupUrl.includes('?') ? `${lookupUrl}&curp=${encodeURIComponent(curp)}` : `${lookupUrl}?curp=${encodeURIComponent(curp)}`;
                        const response = await fetch(url, { headers: { 'Accept': 'application/json' }});
                        if (!response.ok) throw new Error('Búsqueda sin resultados');
                        const payload = await response.json();
                        if (!payload || !payload.id) {
                            throw new Error('No se encontró el beneficiario');
                        }
                        hiddenId.value = payload.id;
                        setResult(`Beneficiario: ${payload.nombre ?? payload.name ?? curp}`, 'success');
                        curpInput.value = curp;
                        validateField(rules[0]);
                    } catch (error) {
                        hiddenId.value = '';
                        setResult(error.message || 'No se encontró el beneficiario.', 'danger');
                        validateField(rules[0]);
                    }
                });
            }

            form.addEventListener('submit', event => {
                let valid = true;
                rules.forEach(rule => {
                    valid = validateField(rule) && valid;
                });

                if (!valid) {
                    event.preventDefault();
                    event.stopPropagation();
                    const firstInvalid = form.querySelector('.is-invalid');
                    firstInvalid?.focus();
                }
            });
        });
    </script>
@endpush
