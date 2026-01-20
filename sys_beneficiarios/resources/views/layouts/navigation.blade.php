@php
    $user = Auth::user();
    $isAdmin = $user?->hasRole('admin');
    $isCapturista = $user?->hasRole('capturista');
    $homeRoute = $isAdmin
        ? route('admin.home')
        : ($isCapturista ? route('capturista.home') : route('dashboard'));

    $dashboardActive = $isAdmin
        ? (request()->routeIs('admin.home') || request()->routeIs('admin.kpis'))
        : ($isCapturista
            ? (request()->routeIs('capturista.home') || request()->routeIs('capturista.kpis'))
            : request()->routeIs('dashboard'));

    $primaryLinks = [
        [
            'label' => 'Dashboard',
            'route' => $homeRoute,
            'icon' => 'bi-speedometer2',
            'active' => $dashboardActive,
        ],
    ];

    if ($isAdmin) {
        $primaryLinks[] = [
            'label' => 'Beneficiarios',
            'route' => route('admin.beneficiarios.index'),
            'icon' => 'bi-people',
            'active' => request()->routeIs('beneficiarios.*') || request()->routeIs('admin.beneficiarios.*'),
        ];
    }

    if ($isAdmin || $isCapturista) {
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
                'label' => 'Themes',
                'route' => route('admin.themes.current.show'),
                'pattern' => 'admin.themes.*',
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
            <span class="badge bg-light text-primary fw-bold">IPJ</span>
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
    <div class="bg-dark border-bottom border-1 border-white border-opacity-10">
        <div class="container py-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="text-white-50 text-uppercase small">Atajos</span>
                @foreach($quickLinks as $link)
                    <a
                        class="btn btn-sm {{ ($link['disabled'] ?? false) ? 'btn-outline-secondary disabled' : 'btn-outline-light' }}"
                        href="{{ $link['disabled'] ?? false ? '#' : $link['route'] }}">
                        {{ __($link['label']) }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
