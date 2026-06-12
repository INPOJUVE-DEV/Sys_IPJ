@php
    $user = Auth::user();
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

    $dashboardActive = $isAdmin
        ? (request()->routeIs('admin.home') || request()->routeIs('admin.kpis'))
        : ($isDelegado
            ? request()->routeIs('delegacion.home')
            : ($isCapturista
                ? (request()->routeIs('capturista.home') || request()->routeIs('capturista.kpis'))
                : ($isSkatePlaza
                    ? request()->routeIs('skate-plaza.*')
                    : request()->routeIs('dashboard'))));

    $primaryLinks = [];
    if (! $isCapturistaProgramas) {
        $primaryLinks[] = [
            'label' => 'Dashboard',
            'route' => $homeRoute,
            'icon' => 'bi-speedometer2',
            'active' => $dashboardActive,
        ];
    }

    if ($isAdmin || $isDelegado) {
        $primaryLinks[] = [
            'label' => 'Stack',
            'route' => route('stack.index'),
            'icon' => 'bi-stack',
            'active' => request()->routeIs('stack.*') || request()->routeIs('admin.inventario.tarjetas.*') || request()->routeIs('delegacion.inventario.tarjetas.*'),
        ];
        $primaryLinks[] = [
            'label' => 'Eventos',
            'route' => route('eventos.index'),
            'icon' => 'bi-calendar-event',
            'active' => request()->routeIs('eventos.*'),
        ];
    }

    if ($isAdmin) {
        $primaryLinks[] = [
            'label' => 'Beneficiarios',
            'route' => route('admin.beneficiarios.index'),
            'icon' => 'bi-people',
            'active' => request()->routeIs('beneficiarios.*') || request()->routeIs('admin.beneficiarios.*'),
        ];
        $primaryLinks[] = [
            'label' => 'Programas',
            'route' => route('programas.index'),
            'icon' => 'bi-collection',
            'active' => request()->routeIs('programas.*'),
        ];
    }

    if ($isAdmin || $isDelegado || $isCapturista) {
        $primaryLinks[] = [
            'label' => 'Captura',
            'route' => route('beneficiarios.create'),
            'icon' => 'bi-plus-circle',
            'active' => request()->routeIs('beneficiarios.create'),
        ];
        $primaryLinks[] = [
            'label' => 'Domicilios',
            'route' => route('domicilios.index'),
            'icon' => 'bi-geo-alt',
            'active' => request()->routeIs('domicilios.*'),
        ];
    }

    if ($isAdmin || $isDelegado || $isCapturista || $isCapturistaProgramas) {
        $primaryLinks[] = [
            'label' => 'Inscripciones',
            'route' => route('inscripciones.index'),
            'icon' => 'bi-calendar-check',
            'active' => request()->routeIs('inscripciones.*'),
        ];
    }

    if ($isCapturista) {
        $primaryLinks[] = [
            'label' => 'Mis registros',
            'route' => route('mis-registros.index'),
            'icon' => 'bi-clipboard-check',
            'active' => request()->routeIs('mis-registros.*'),
        ];
    }

    $adminTools = [];
    if ($isAdmin) {
        $adminTools = [
            [
                'label' => 'Usuarios',
                'route' => route('admin.usuarios.index'),
                'pattern' => 'admin.usuarios.*',
            ],
            [
                'label' => 'Oficinas',
                'route' => route('admin.oficinas.index'),
                'pattern' => 'admin.oficinas.*',
            ],
            [
                'label' => 'Tarjetas',
                'route' => route('admin.inventario.tarjetas.index'),
                'pattern' => 'admin.inventario.tarjetas.*',
            ],
            [
                'label' => 'Protecciones',
                'route' => route('admin.inventario.protecciones.index'),
                'pattern' => 'admin.inventario.protecciones.*',
            ],
            [
                'label' => 'Movimientos',
                'route' => route('admin.inventario.movimientos.index'),
                'pattern' => 'admin.inventario.movimientos.*',
            ],
            [
                'label' => 'Catálogos',
                'route' => route('admin.catalogos.index'),
                'pattern' => 'admin.catalogos.*',
            ],
            [
                'label' => 'Componentes',
                'route' => route('admin.components.index'),
                'pattern' => 'admin.components.*',
            ],
            [
                'label' => 'Tipos de evento',
                'route' => route('admin.evento-tipos.index'),
                'pattern' => 'admin.evento-tipos.*',
            ],
            [
                'label' => 'Themes',
                'route' => route('admin.themes.current.show'),
                'pattern' => 'admin.themes.*',
            ],
            [
                'label' => 'Integraciones',
                'route' => route('admin.integraciones.api_tj.sync-runs.index'),
                'pattern' => 'admin.integraciones.api_tj.*',
            ],
        ];
    }

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
                @foreach($primaryLinks as $link)
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center {{ $link['active'] ? 'active' : '' }}" href="{{ $link['route'] }}">
                            <i class="bi {{ $link['icon'] }} me-1"></i>
                            <span>{{ __($link['label']) }}</span>
                        </a>
                    </li>
                @endforeach

                @if(!empty($adminTools))
                    @php
                        $adminActive = collect($adminTools)->contains(fn($tool) => request()->routeIs($tool['pattern']));
                    @endphp
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $adminActive ? 'active' : '' }}" href="#" id="adminToolsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-shield-lock me-1"></i> Administración
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
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <span>{{ $user?->name }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="userDropdown">
                        <li class="dropdown-header text-uppercase small">Sesión</li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-gear me-2"></i>Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
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
                    <a
                        class="btn btn-sm {{ ($link['disabled'] ?? false) ? 'btn-outline-secondary disabled' : 'btn-outline-secondary' }}"
                        href="{{ $link['disabled'] ?? false ? '#' : $link['route'] }}">
                        {{ __($link['label']) }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
