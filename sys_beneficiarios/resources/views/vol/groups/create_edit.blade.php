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
            <form action="{{ $action }}" method="POST" class="row g-3">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                <div class="col-md-6">
                    <label class="form-label">Programa</label>
                    <select name="program_id" class="form-select" required>
                        <option value="">Seleccione</option>
                        @foreach($programs as $id => $name)
                            <option value="{{ $id }}" @selected(old('program_id', $group->program_id ?? '') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Sede</label>
                    <select name="site_id" class="form-select" required>
                        <option value="">Seleccione</option>
                        @foreach($sites as $id => $name)
                            <option value="{{ $id }}" @selected(old('site_id', $group->site_id ?? '') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nombre del grupo</label>
                    <input type="text" name="name" value="{{ old('name', $group->name ?? '') }}" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="type" class="form-select" required>
                        @foreach(['semanal' => 'Semanal', 'sabatino' => 'Sabatino'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $group->type ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Plantilla de horario</label>
                    <select name="schedule_template" class="form-select" required>
                        @foreach(['lmv' => 'Lunes a miercoles', 'mj' => 'Jueves y viernes', 'sab' => 'Sabado'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('schedule_template', $group->schedule_template ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha de inicio</label>
                    <input type="date" name="start_date" value="{{ old('start_date', optional($group->start_date ?? null)->format('Y-m-d')) }}" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha de cierre</label>
                    <input type="date" name="end_date" value="{{ old('end_date', optional($group->end_date ?? null)->format('Y-m-d')) }}" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Capacidad</label>
                    <input type="number" min="0" name="capacity" value="{{ old('capacity', $group->capacity ?? 12) }}" class="form-control" required>
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