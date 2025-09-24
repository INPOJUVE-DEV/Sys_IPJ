<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 m-0">Usuarios</h2>
            <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">Nuevo usuario</a>
        </div>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-dark border-0">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-lg-6">
                    <label for="userSearch" class="form-label small text-uppercase text-muted mb-1">Buscar usuario</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-secondary text-white"><i class="bi bi-search"></i></span>
                        <input type="search" id="userSearch" class="form-control" placeholder="Nombre o correo">
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3" id="roleChips">
                @php($roles = ['all' => 'Todos', 'admin' => 'Admin', 'encargado_360' => 'Encargado', 'psicologo' => 'Psicólogo', 'capturista' => 'Capturista'])
                @foreach($roles as $roleKey => $roleLabel)
                    <button type="button" class="btn btn-sm btn-outline-light @if($loop->first) active @endif" data-role="{{ $roleKey }}">{{ $roleLabel }}</button>
                @endforeach
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    @forelse($users as $user)
                        @php($roleNames = $user->roles->pluck('name')->map(fn($name) => \Illuminate\Support\Str::of($name)->lower()->slug('-'))->toArray())
                        <tr data-name="{{ \Illuminate\Support\Str::lower($user->name) }}" data-email="{{ \Illuminate\Support\Str::lower($user->email) }}" data-roles="{{ implode(' ', $roleNames) }}">
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.usuarios.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                <form action="{{ route('admin.usuarios.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar usuario?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">Sin usuarios</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small" id="usersSummary"></div>
            @if($users->hasPages())
                <div>{{ $users->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('userSearch');
    const chipContainer = document.getElementById('roleChips');
    const rows = Array.from(document.querySelectorAll('#usersTableBody tr'));
    const summary = document.getElementById('usersSummary');
    let currentRole = 'all';

    const normalize = (value) => (value || '').toString().trim().toLowerCase();

    function applyFilters() {
        const query = normalize(searchInput.value);
        let visible = 0;
        rows.forEach(row => {
            const matchesRole = currentRole === 'all' || row.dataset.roles.split(' ').includes(currentRole);
            const matchesQuery = !query || row.dataset.name.includes(query) || row.dataset.email.includes(query);
            const shouldShow = matchesRole && matchesQuery;
            row.classList.toggle('d-none', !shouldShow);
            if (shouldShow) visible++;
        });
        if (summary) {
            const total = rows.length;
            summary.textContent = visible === total
                ? `${total} usuarios` : `${visible} de ${total} usuarios mostrados`;
        }
    }

    if (chipContainer) {
        chipContainer.querySelectorAll('button[data-role]').forEach(btn => {
            btn.addEventListener('click', () => {
                chipContainer.querySelectorAll('button[data-role]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentRole = btn.dataset.role || 'all';
                applyFilters();
            });
        });
    }

    searchInput?.addEventListener('input', applyFilters);
    applyFilters();
});
</script>
@endpush

