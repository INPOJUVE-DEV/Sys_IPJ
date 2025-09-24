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
        <div class="card-body">
            <div id="usersGrid" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                @forelse($users as $user)
                    @php($roleNames = $user->roles->pluck('name')->map(fn($name) => \Illuminate\Support\Str::of($name)->lower()->slug('-'))->toArray())
                    <div class="col" data-user-card data-name="{{ \Illuminate\Support\Str::lower($user->name) }}" data-email="{{ \Illuminate\Support\Str::lower($user->email) }}" data-roles="{{ implode(' ', $roleNames) }}">
                        <div class="card bg-dark border border-white text-white h-100 shadow-sm">
                            <div class="card-body d-flex flex-column gap-3">
                                <div>
                                    <h3 class="h6 text-white mb-1">{{ $user->name }}</h3>
                                    <div class="small text-white-50"><i class="bi bi-envelope me-1"></i>{{ $user->email }}</div>
                                </div>
                                <div class="text-white-50 small">
                                    <i class="bi bi-person-badge me-1"></i>{{ $user->roles->pluck('name')->join(', ') }}
                                </div>
                                <div class="mt-auto d-flex flex-column gap-2">
                                    <a href="{{ route('admin.usuarios.edit', $user) }}" class="btn btn-outline-light btn-sm w-100">
                                        <i class="bi bi-pencil-square me-1"></i>Editar
                                    </a>
                                    <form action="{{ route('admin.usuarios.destroy', $user) }}" method="POST" class="m-0" onsubmit="return confirm('¿Eliminar usuario?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="bi bi-trash me-1"></i>Eliminar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center text-muted py-4">Sin usuarios</div>
                    </div>
                @endforelse
            </div>
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
    const cards = Array.from(document.querySelectorAll('[data-user-card]'));
    const summary = document.getElementById('usersSummary');
    let currentRole = 'all';

    const normalize = (value) => (value || '').toString().trim().toLowerCase();

    function applyFilters() {
        const query = normalize(searchInput.value);
        let visible = 0;
        cards.forEach(card => {
            const roles = (card.dataset.roles || '').split(' ').filter(Boolean);
            const matchesRole = currentRole === 'all' || roles.includes(currentRole);
            const matchesQuery = !query || (card.dataset.name || '').includes(query) || (card.dataset.email || '').includes(query);
            const shouldShow = matchesRole && matchesQuery;
            card.classList.toggle('d-none', !shouldShow);
            if (shouldShow) visible++;
        });
        if (summary) {
            const total = cards.length;
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

