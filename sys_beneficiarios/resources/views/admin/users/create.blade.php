<x-app-layout>
    @php($userRoutes = request()->routeIs('delegacion.*') ? 'delegacion.usuarios' : 'admin.usuarios')
    @php($canAssignMunicipios = auth()->user()?->hasRole('admin'))

    <x-slot name="header">
        <h2 class="h4 m-0">Nuevo usuario</h2>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route($userRoutes.'.store') }}" novalidate>
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nombre completo</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required class="form-control @error('name') is-invalid @enderror">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Correo de acceso</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required class="form-control @error('email') is-invalid @enderror">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Contrasena temporal</label>
                        <input id="password" name="password" type="password" required class="form-control @error('password') is-invalid @enderror">
                        <div class="form-text">Minimo 8 caracteres, con mayusculas, minusculas y numeros.</div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="role" class="form-label">Que hara esta persona?</label>
                        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="" disabled @selected(! old('role'))>Selecciona una opcion</option>
                            @foreach($roles as $value => $label)
                                <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="oficina_id" class="form-label">Region de trabajo</label>
                        <select id="oficina_id" name="oficina_id" class="form-select @error('oficina_id') is-invalid @enderror">
                            <option value="">Sin region</option>
                            @foreach($offices as $office)
                                <option value="{{ $office->id }}" data-region="{{ $office->region }}" @selected((string) old('oficina_id') === (string) $office->id)>
                                    {{ $office->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Obligatoria para delegados, capturistas y capturistas de programas.</div>
                        @error('oficina_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                @if($canAssignMunicipios)
                    <div class="card bg-light border-0 mt-4 d-none" id="municipiosPanel" data-has-old="{{ old('municipio_ids') ? '1' : '0' }}">
                        <div class="card-body">
                            <input type="hidden" name="municipio_ids_present" value="1">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                <div>
                                    <div class="fw-semibold">Municipios que atendera el delegado</div>
                                    <div class="small text-muted">Por default se marcan todos los municipios de la region. Puedes quitar los que no correspondan.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-check-visible>Marcar todos</button>
                            </div>

                            @forelse($municipiosByRegion as $region => $municipios)
                                <div class="row g-2 d-none" data-region-group="{{ $region }}">
                                    @foreach($municipios as $municipio)
                                        <div class="col-sm-6 col-lg-4">
                                            <label class="form-check border rounded bg-white p-2 h-100">
                                                <input class="form-check-input ms-0 me-2" type="checkbox" name="municipio_ids[]" value="{{ $municipio->id }}" @checked(collect(old('municipio_ids', []))->contains((string) $municipio->id))>
                                                <span class="form-check-label">{{ $municipio->nombre }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            @empty
                                <div class="text-muted">Primero importa el catalogo de municipios.</div>
                            @endforelse
                        </div>
                    </div>
                @endif

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route($userRoutes.'.index') }}" class="btn btn-outline-secondary me-2"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                    <button type="submit" class="btn btn-cta"><i class="bi bi-person-plus me-1"></i>Guardar usuario</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const role = document.getElementById('role');
                const office = document.getElementById('oficina_id');
                const panel = document.getElementById('municipiosPanel');
                const checkVisible = document.querySelector('[data-check-visible]');

                const visibleGroup = () => {
                    if (!panel || !office) return null;
                    const selected = office.options[office.selectedIndex];
                    const region = selected?.dataset?.region || '';
                    return panel.querySelector(`[data-region-group="${region}"]`);
                };

                const markVisible = () => {
                    const group = visibleGroup();
                    group?.querySelectorAll('input[type="checkbox"]').forEach(input => input.checked = true);
                };

                const updatePanel = () => {
                    if (!panel || !role || !office) return;
                    const selected = office.options[office.selectedIndex];
                    const region = selected?.dataset?.region || '';
                    const show = role.value === 'delegado' && !!region;

                    panel.classList.toggle('d-none', !show);
                    panel.querySelectorAll('[data-region-group]').forEach(group => {
                        group.classList.toggle('d-none', group.dataset.regionGroup !== region);
                    });
                    panel.querySelectorAll('[data-region-group].d-none input[type="checkbox"]').forEach(input => input.checked = false);

                    if (show && panel.dataset.hasOld !== '1') {
                        markVisible();
                    }
                };

                role?.addEventListener('change', updatePanel);
                office?.addEventListener('change', updatePanel);
                checkVisible?.addEventListener('click', markVisible);
                updatePanel();
            });
        </script>
    @endpush
</x-app-layout>
