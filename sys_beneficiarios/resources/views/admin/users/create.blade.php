<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 m-0">Nuevo usuario</h2>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.usuarios.store') }}" novalidate>
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nombre</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required class="form-control @error('name') is-invalid @enderror">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required class="form-control @error('email') is-invalid @enderror">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Contraseña</label>
                        <input id="password" name="password" type="password" required class="form-control @error('password') is-invalid @enderror">
                        <div class="form-text">Mín. 8, mayúsculas, minúsculas y números.</div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="role" class="form-label">Rol</label>
                        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="" disabled selected>Selecciona rol...</option>
                            @foreach($roles as $value => $label)
                                <option value="{{ $value }}" @selected(old('role')===$value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary me-2"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                    <button type="submit" class="btn btn-cta"><i class="bi bi-person-plus me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
