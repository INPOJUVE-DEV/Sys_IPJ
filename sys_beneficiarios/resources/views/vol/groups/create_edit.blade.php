<x-app-layout>
    @php
        $isEdit = isset($group);
        $title = $isEdit ? 'Editar grupo' : 'Crear grupo';
        $action = $isEdit ? route('vol.groups.update', $group) : route('vol.groups.store');
    @endphp

    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1 class="h4 m-0">{{ $title }}</h1>
            <a href="{{ route('vol.groups.index') }}" class="btn btn-outline-light">Volver</a>
        </div>
    </x-slot>

    <div class="card">
        <div class="card-body">
            <form id="vol-group-form" action="{{ $action }}" method="POST" class="row g-3 needs-validation" novalidate>
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                <div class="col-md-6">
                    <label class="form-label">Sede</label>
                    <select name="site_id" class="form-select" required>
                        <option value="">Seleccione</option>
                        @foreach($sites as $id => $name)
                            <option value="{{ $id }}" @selected(old('site_id', $group->site_id ?? '') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" data-feedback-for="site_id"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nombre del grupo</label>
                    <input type="text" name="name" value="{{ old('name', $group->name ?? '') }}" class="form-control" required>
                    <div class="invalid-feedback" data-feedback-for="name"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="type" class="form-select" required>
                        @foreach(['semanal' => 'Semanal', 'sabatino' => 'Sabatino'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $group->type ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" data-feedback-for="type"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Plantilla de horario</label>
                    <select name="schedule_template" class="form-select" required>
                        @foreach(['lmv' => 'Lunes a miercoles', 'mj' => 'Jueves y viernes', 'sab' => 'Sabado'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('schedule_template', $group->schedule_template ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" data-feedback-for="schedule_template"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha de inicio</label>
                    <input type="date" name="start_date" value="{{ old('start_date', optional($group->start_date ?? null)->format('Y-m-d')) }}" class="form-control" required>
                    <div class="invalid-feedback" data-feedback-for="start_date"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Capacidad</label>
                    <input type="number" min="0" name="capacity" value="{{ old('capacity', $group->capacity ?? 12) }}" class="form-control" required>
                    <div class="invalid-feedback" data-feedback-for="capacity"></div>
                </div>

                <div class="col-12">
                    <small class="text-muted">La fecha de cierre se calculara automaticamente como el ultimo dia del mes de inicio.</small>
                </div>

                @if($isEdit)
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="state" class="form-select">
                            @foreach(['borrador' => 'Borrador', 'publicado' => 'Publicado', 'cerrado' => 'Cerrado'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('state', $group->state ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Actualizar grupo' : 'Guardar grupo' }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('vol-group-form');
            if (!form) return;

            const escapeSelector = value => {
                if (window.CSS && typeof window.CSS.escape === 'function') {
                    return window.CSS.escape(value);
                }
                return value.replace(/([ #;?%&,.+*~':"!^$\[\]()=>|\/])/g, '\\$1');
            };

            const rules = [
                {
                    name: 'site_id',
                    validate: value => value !== '',
                    message: 'Seleccione una sede válida.',
                    events: ['change'],
                },
                {
                    name: 'name',
                    validate: value => value.length >= 4,
                    message: 'El nombre debe tener al menos 4 caracteres.',
                    events: ['input', 'blur'],
                },
                {
                    name: 'type',
                    validate: value => value !== '',
                    message: 'Seleccione el tipo de grupo.',
                    events: ['change'],
                },
                {
                    name: 'schedule_template',
                    validate: value => value !== '',
                    message: 'Elija una plantilla de horario.',
                    events: ['change'],
                },
                {
                    name: 'start_date',
                    validate: value => !!value,
                    message: 'Indique la fecha de inicio.',
                    events: ['change', 'input'],
                },
                {
                    name: 'capacity',
                    validate: value => {
                        const numeric = Number(value);
                        return Number.isFinite(numeric) && numeric > 0;
                    },
                    message: 'Capture una capacidad mayor a cero.',
                    events: ['input', 'change'],
                },
            ];

            function setValidity(fieldName) {
                const config = rules.find(rule => rule.name === fieldName);
                if (!config) return true;

                const input = form.elements.namedItem(fieldName);
                if (!input) return true;

                const value = (input.value || '').trim();
                const isValid = config.validate(value, input);
                const message = isValid === true ? '' : (typeof isValid === 'string' ? isValid : config.message);

                input.classList.toggle('is-invalid', !!message);
                if (!message && value !== '') {
                    input.classList.add('is-valid');
                } else {
                    input.classList.remove('is-valid');
                }

                if (typeof input.setCustomValidity === 'function') {
                    input.setCustomValidity(message);
                }

                const selector = `.invalid-feedback[data-feedback-for="${escapeSelector(fieldName)}"]`;
                const feedback = form.querySelector(selector);
                if (feedback) {
                    feedback.textContent = message;
                }

                return !message;
            }

            rules.forEach(rule => {
                const input = form.elements.namedItem(rule.name);
                if (!input) return;
                (rule.events || ['input']).forEach(eventName => {
                    input.addEventListener(eventName, () => setValidity(rule.name));
                });
                setValidity(rule.name);
            });

            form.addEventListener('submit', event => {
                let valid = true;
                rules.forEach(rule => {
                    valid = setValidity(rule.name) && valid;
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
