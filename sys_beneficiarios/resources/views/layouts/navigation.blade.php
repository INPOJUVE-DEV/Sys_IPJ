<nav class="navbar navbar-expand-lg navbar-dark bg-primary border-bottom sticky-top py-0">
    <div class="container-fluid px-0">
        <a class="navbar-brand d-flex align-items-center" href="{{ Auth::user() && Auth::user()->hasRole('admin') ? route('admin.home') : (Auth::user() && Auth::user()->hasRole('encargado_360') ? route('s360.enc360.view') : (Auth::user() && Auth::user()->hasRole('capturista') ? route('capturista.home') : route('dashboard'))) }}">
            <span class="fw-semibold text-white">Sys_IPJ</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    @if(Auth::user() && Auth::user()->hasRole('admin'))
                        <a class="nav-link {{ request()->routeIs('admin.home') ? 'active' : '' }}" href="{{ route('admin.home') }}">{{ __('Dashboard') }}</a>
                    
                    @elseif(Auth::user() && Auth::user()->hasRole('capturista'))
                        <a class="nav-link {{ request()->routeIs('capturista.home') ? 'active' : '' }}" href="{{ route('capturista.home') }}">{{ __('Dashboard') }}</a>
                    @elseif(Auth::user() && Auth::user()->hasRole('psicologo'))
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">{{ __('Mis Pacientes') }}</a>
                    @else
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                    @endif
                </li>
                @role('admin|capturista')
                <li class="nav-item">
                    <a class="nav-link {{ (Auth::user()->hasRole('admin') ? request()->routeIs('admin.beneficiarios.*') : request()->routeIs('beneficiarios.*')) ? 'active' : '' }}" href="{{ Auth::user()->hasRole('admin') ? route('admin.beneficiarios.index') : route('beneficiarios.index') }}">{{ __('Beneficiarios') }}</a>
                </li>
                @endrole
                @if(!Auth::user() || !Auth::user()->hasRole('psicologo'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('beneficiarios.create') ? 'active' : '' }}" href="{{ route('beneficiarios.create') }}">{{ __('Captura') }}</a>
                </li>
                @endif
                @role('admin|capturista')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('domicilios.*') ? 'active' : '' }}" href="{{ route('domicilios.index') }}">{{ __('Domicilios') }}</a>
                </li>
                @endrole
                @role('admin')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}" href="{{ route('admin.usuarios.index') }}">{{ __('Usuarios') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.catalogos.*') ? 'active' : '' }}" href="{{ route('admin.catalogos.index') }}">{{ __('Catalogos') }}</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->is('vol/*') ? 'active' : '' }}" href="#" id="volDropdownAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Jovenes al Volante
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="volDropdownAdmin">
                        @if(auth()->user()->can('vol.reports.view'))
                            <li><a class="dropdown-item" href="{{ route('vol.dashboard') }}">Dashboard</a></li>
                        @endif
                        @can('viewAny', App\Models\VolGroup::class)
                            <li><a class="dropdown-item" href="{{ route('vol.groups.index') }}">Grupos</a></li>
                        @endcan
                        @can('create', App\Models\VolPayment::class)
                            <li><a class="dropdown-item" href="{{ route('vol.payments.create') }}">Registrar pago</a></li>
                        @endcan
                        @can('viewAny', App\Models\VolSite::class)
                            <li><a class="dropdown-item" href="{{ route('vol.sites.index') }}">Sedes</a></li>
                        @endcan
                    </ul>
                </li>
                <!-- Salud360 (Admin) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->is('s360/*') ? 'active' : '' }}" href="#" id="s360Dropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Salud360
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="s360Dropdown">
                        <li><a class="dropdown-item" href="{{ route('s360.admin.dash') }}">Admin Dash</a></li>
                        <li><a class="dropdown-item" href="{{ route('s360.bienestar.view') }}">Bienestar Dash</a></li>
                        <li><a class="dropdown-item" href="{{ route('s360.enc360.view') }}">Enc360 Dash</a></li>
                    </ul>
                </li>
                @endrole

@role('encargado_bienestar')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('s360.bienestar.*') ? 'active' : '' }}" href="{{ route('s360.bienestar.view') }}">Bienestar Dash</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->is('vol/*') ? 'active' : '' }}" href="#" id="volDropdownBienestar" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Jovenes al Volante
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="volDropdownBienestar">
                        @if(auth()->user()->can('vol.reports.view'))
                            <li><a class="dropdown-item" href="{{ route('vol.dashboard') }}">Dashboard</a></li>
                        @endif
                        @can('viewAny', App\Models\VolGroup::class)
                            <li><a class="dropdown-item" href="{{ route('vol.groups.index') }}">Grupos</a></li>
                        @endcan
                        @can('create', App\Models\VolPayment::class)
                            <li><a class="dropdown-item" href="{{ route('vol.payments.create') }}">Registrar pago</a></li>
                        @endcan
                        @can('viewAny', App\Models\VolSite::class)
                            <li><a class="dropdown-item" href="{{ route('vol.sites.index') }}">Sedes</a></li>
                        @endcan
                    </ul>
                </li>
@endrole

@role('encargado_360')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('s360.enc360.view') ? 'active' : '' }}" href="{{ route('s360.enc360.view') }}">Dashboard</a>
                </li>
@endrole

@hasanyrole('encargado_JAV|encargado_jav')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->is('vol/*') ? 'active' : '' }}" href="#" id="volDropdownJav" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Jovenes al Volante
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="volDropdownJav">
                        @if(auth()->user()->can('vol.reports.view'))
                            <li><a class="dropdown-item" href="{{ route('vol.dashboard') }}">Dashboard</a></li>
                        @endif
                        @can('viewAny', App\Models\VolGroup::class)
                            <li><a class="dropdown-item" href="{{ route('vol.groups.index') }}">Grupos</a></li>
                        @endcan
                        @can('create', App\Models\VolPayment::class)
                            <li><a class="dropdown-item" href="{{ route('vol.payments.create') }}">Registrar pago</a></li>
                        @endcan
                    </ul>
                </li>
@endhasanyrole

                @role('psicologo')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('s360.psico.view') ? 'active' : '' }}" href="{{ route('s360.psico.view') }}">Mis Pacientes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('s360.psico.sesiones.*') ? 'active' : '' }}" href="{{ route('s360.psico.view') }}">Historial</a>
                </li>
                @endrole
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ Auth::user()->name }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}">{{ __('Profile') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">{{ __('Log Out') }}</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
