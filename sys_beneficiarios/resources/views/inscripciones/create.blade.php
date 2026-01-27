<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Nueva inscripcion</h2></x-slot>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('inscripciones.store') }}" novalidate>
                @csrf

                <div class="card border border-white border-opacity-10 mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="programa_id" class="form-label">Programa</label>
                                <select id="programa_id" name="programa_id" class="form-select @error('programa_id') is-invalid @enderror" required>
                                    <option value="">Selecciona</option>
                                    @foreach($programas as $programa)
                                        <option value="{{ $programa->id }}" data-renovable="{{ $programa->renovable ? '1' : '0' }}" @selected(old('programa_id') == $programa->id)>
                                            {{ $programa->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('programa_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label for="periodo" class="form-label">Periodo</label>
                                <input id="periodo" type="month" name="periodo" value="{{ old('periodo', $periodo ?? '') }}" class="form-control @error('periodo') is-invalid @enderror" required>
                                @error('periodo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <label for="estatus" class="form-label">Estatus</label>
                                <select id="estatus" name="estatus" class="form-select @error('estatus') is-invalid @enderror">
                                    @foreach(['inscrito' => 'Inscrito', 'baja' => 'Baja', 'lista_espera' => 'Lista de espera'] as $key => $label)
                                        <option value="{{ $key }}" @selected(old('estatus', 'inscrito') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('estatus')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2 d-flex align-items-center">
                                <div class="form-check mt-4" id="renovacionWrap" style="display:none;">
                                    <input type="hidden" name="renovacion" value="0">
                                    <input id="renovacion" class="form-check-input" type="checkbox" name="renovacion" value="1" @checked(old('renovacion'))>
                                    <label for="renovacion" class="form-check-label">Renovacion</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @include('inscripciones.partials.beneficiario-form', ['municipios' => $municipios])

                <div class="d-flex justify-content-end mt-3">
                    <a href="{{ route('inscripciones.list') }}" class="btn btn-outline-secondary me-2"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                    <button class="btn btn-cta" type="submit"><i class="bi bi-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const select = document.getElementById('programa_id');
        const wrap = document.getElementById('renovacionWrap');
        const checkbox = document.getElementById('renovacion');
        const update = () => {
            const option = select?.options?.[select.selectedIndex];
            const renovable = option?.dataset?.renovable === '1';
            if (wrap) {
                wrap.style.display = renovable ? 'block' : 'none';
            }
            if (!renovable && checkbox) {
                checkbox.checked = false;
            }
        };
        select?.addEventListener('change', update);
        update();
    });
    </script>
    @endpush
</x-app-layout>
