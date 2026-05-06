@php
    $user = Auth::user();
    $routeName = request()->route()?->getName() ?? '';
    $isAdmin = $user?->hasRole('admin');
    $isDelegado = $user?->hasRole('delegado');
    $isCapturista = $user?->hasRole('capturista');
    $isCapturistaProgramas = $user?->hasRole('capturista_programas');
    $isSkatePlaza = $user?->hasRole('skate_plaza');

    $homeRoute = $isAdmin
        ? route('admin.home')
        : ($isDelegado
            ? route('delegacion.home')
            : ($isCapturistaProgramas
                ? route('inscripciones.index')
                : ($isCapturista
                    ? route('capturista.home')
                    : ($isSkatePlaza ? route('skate-plaza.home') : route('dashboard')))));

    $dashboardLinks = [];
    if (! $isCapturistaProgramas) {
        $dashboardLinks[] = [
            'label' => $isAdmin ? 'Dashboard general' : ($isDelegado ? 'Dashboard regional' : 'Dashboard'),
            'route' => $homeRoute,
            'icon' => 'bi-speedometer2',
            'active' => $isAdmin
                ? request()->routeIs('admin.home') || request()->routeIs('admin.kpis')
                : ($isDelegado
                    ? request()->routeIs('delegacion.home')
                    : ($isCapturista
                        ? request()->routeIs('capturista.home') || request()->routeIs('capturista.kpis')
                        : request()->routeIs('dashboard'))),
        ];
    }

    if ($isAdmin) {
        $dashboardLinks[] = [
            'label' => 'Indicadores',
            'route' => route('admin.indicadores'),
            'icon' => 'bi-graph-up-arrow',
            'active' => request()->routeIs('admin.indicadores*'),
        ];
    }

    $tarjetaJovenLinks = [];
    if ($isAdmin || $isDelegado) {
        $tarjetaJovenLinks[] = [
            'label' => 'Inventario',
            'route' => route('inventario.index'),
            'icon' => 'bi-box-seam',
            'active' => request()->routeIs('inventario.*')
                || request()->routeIs('stack.*')
                || request()->routeIs('admin.inventario.tarjetas.*')
                || request()->routeIs('delegacion.inventario.tarjetas.*')
                || request()->routeIs('admin.inventario.protecciones.*')
                || request()->routeIs('admin.inventario.movimientos.*'),
        ];
    }

    if ($isAdmin || $isDelegado || $isCapturista) {
        $tarjetaJovenLinks[] = [
            'label' => 'Captura',
            'route' => route('beneficiarios.create'),
            'icon' => 'bi-plus-circle',
            'active' => in_array($routeName, ['beneficiarios.create', 'beneficiarios.store'], true),
        ];
        $tarjetaJovenLinks[] = [
            'label' => 'Beneficiarios',
            'route' => route('beneficiarios.index'),
            'icon' => 'bi-people',
            'active' => in_array($routeName, ['beneficiarios.index', 'beneficiarios.edit', 'beneficiarios.update', 'beneficiarios.destroy'], true),
        ];
    }

    $eventosLinks = [];
    if ($isAdmin || $isDelegado) {
        $eventosLinks[] = [
            'label' => 'Ver eventos',
            'route' => route('eventos.index'),
            'icon' => 'bi-calendar-event',
            'active' => request()->routeIs('eventos.*'),
        ];
        $eventosLinks[] = [
            'label' => 'Nuevo evento',
            'route' => route('eventos.create'),
            'icon' => 'bi-calendar-plus',
            'active' => request()->routeIs('eventos.create'),
        ];
    }

    if ($isAdmin) {
        $eventosLinks[] = [
            'label' => 'Tipos de evento',
            'route' => route('admin.evento-tipos.index'),
            'icon' => 'bi-tags',
            'active' => request()->routeIs('admin.evento-tipos.*'),
        ];
    }

    $programaLinks = [];
    if ($isAdmin) {
        $programaLinks[] = [
            'label' => 'Programas',
            'route' => route('programas.index'),
            'icon' => 'bi-collection',
            'active' => request()->routeIs('programas.*'),
        ];
    }

    if ($isAdmin || $isDelegado || $isCapturista || $isCapturistaProgramas) {
        $programaLinks[] = [
            'label' => 'Inscripciones',
            'route' => route('inscripciones.index'),
            'icon' => 'bi-calendar-check',
            'active' => request()->routeIs('inscripciones.*'),
        ];
    }

    $adminTools = [];
    if ($isAdmin) {
        $adminTools = [
            ['label' => 'Usuarios', 'route' => route('admin.usuarios.index'), 'pattern' => 'admin.usuarios.*'],
            ['label' => 'Oficinas', 'route' => route('admin.oficinas.index'), 'pattern' => 'admin.oficinas.*'],
            ['label' => 'Tarjetas', 'route' => route('admin.inventario.tarjetas.index'), 'pattern' => 'admin.inventario.tarjetas.*'],
            ['label' => 'Protecciones', 'route' => route('admin.inventario.protecciones.index'), 'pattern' => 'admin.inventario.protecciones.*'],
            ['label' => 'Movimientos', 'route' => route('admin.inventario.movimientos.index'), 'pattern' => 'admin.inventario.movimientos.*'],
            ['label' => 'API TJ', 'route' => route('admin.api-tj.requests.index'), 'pattern' => 'admin.api-tj.*'],
            ['label' => 'Catalogos', 'route' => route('admin.catalogos.index'), 'pattern' => 'admin.catalogos.*'],
            ['label' => 'Componentes', 'route' => route('admin.components.index'), 'pattern' => 'admin.components.*'],
            ['label' => 'Themes', 'route' => route('admin.themes.current.show'), 'pattern' => 'admin.themes.*'],
        ];
    }

    $menuGroups = collect([
        ['label' => 'Dashboards', 'icon' => 'bi-grid-1x2', 'links' => $dashboardLinks],
        ['label' => 'Tarjeta Joven', 'icon' => 'bi-person-vcard', 'links' => $tarjetaJovenLinks],
        ['label' => 'Eventos', 'icon' => 'bi-calendar-event', 'links' => $eventosLinks],
        ['label' => 'Programas', 'icon' => 'bi-folder2-open', 'links' => $programaLinks],
    ])->filter(fn ($group) => ! empty($group['links']))->values();

    $quickLinks = [];
    if ($isAdmin) {
        $quickLinks[] = ['label' => 'Importar catalogos', 'route' => route('admin.catalogos.index')];
    }
@endphp

<nav class="navbar navbar-expand-lg navbar-dark bg-primary border-bottom sticky-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ $homeRoute }}">
            <span class="badge bg-dark text-white border border-secondary fw-bold">IPJ</span>
            <span class="fw-semibold">Sys Beneficiarios</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                @foreach($menuGroups as $group)
                    @php
                        $groupActive = collect($group['links'])->contains(fn ($link) => $link['active']);
                    @endphp
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $groupActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi {{ $group['icon'] }} me-1"></i>{{ $group['label'] }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark shadow-lg">
                            @foreach($group['links'] as $link)
                                <li>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center {{ $link['active'] ? 'active' : '' }}" href="{{ $link['route'] }}">
                                        <span><i class="bi {{ $link['icon'] }} me-2"></i>{{ __($link['label']) }}</span>
                                        @if($link['active'])
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach

                @if(!empty($adminTools))
                    @php
                        $adminActive = collect($adminTools)->contains(fn ($tool) => request()->routeIs($tool['pattern']));
                    @endphp
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $adminActive ? 'active' : '' }}" href="#" id="adminToolsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-shield-lock me-1"></i> Administracion
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark shadow-lg" aria-labelledby="adminToolsDropdown">
                            @foreach($adminTools as $tool)
                                <li>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center {{ request()->routeIs($tool['pattern']) ? 'active' : '' }}" href="{{ $tool['route'] }}">
                                        <span>{{ __($tool['label']) }}</span>
                                        @if(request()->routeIs($tool['pattern']))
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif

                @if($isCapturista)
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center {{ request()->routeIs('mis-registros.*') ? 'active' : '' }}" href="{{ route('mis-registros.index') }}">
                            <i class="bi bi-clipboard-check me-1"></i>
                            <span>Mis registros</span>
                        </a>
                    </li>
                @endif
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <span>{{ $user?->name }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="userDropdown">
                        <li class="dropdown-header text-uppercase small">Sesion</li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-gear me-2"></i>Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesion</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

@if(!empty($quickLinks))
    <div class="bg-dark border-bottom border-1 border-secondary border-opacity-25">
        <div class="container py-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="text-white-50 text-uppercase small">Atajos</span>
                @foreach($quickLinks as $link)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ $link['route'] }}">
                        {{ __($link['label']) }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
