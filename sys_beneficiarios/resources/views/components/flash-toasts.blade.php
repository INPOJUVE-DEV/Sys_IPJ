@php
    $toasts = [];
    $success = session('status') ?? session('success');
    $routeName = optional(request()->route())->getName();
    $flashActions = collect(session('flash_actions', []))
        ->filter(fn ($action) => is_array($action) && isset($action['label'], $action['url']))
        ->map(fn ($action) => ['label' => $action['label'], 'url' => $action['url']])
        ->values();
    $defaultActions = collect();
    if ($routeName) {
        $maps = [
            'beneficiarios.' => [
                ['label' => 'Crear un nuevo registro', 'route' => 'beneficiarios.create'],
                ['label' => 'Ver listado', 'route' => 'beneficiarios.index'],
            ],
            'vol.groups.' => [
                ['label' => 'Crear nuevo grupo', 'route' => 'vol.groups.create'],
                ['label' => 'Ver grupos', 'route' => 'vol.groups.index'],
            ],
            'vol.payments.' => [
                ['label' => 'Registrar otro pago', 'route' => 'vol.payments.create'],
            ],
        ];
        foreach ($maps as $prefix => $actions) {
            if (\Illuminate\Support\Str::startsWith($routeName, $prefix)) {
                foreach ($actions as $action) {
                    $url = $action['url'] ?? (isset($action['route']) && \Illuminate\Support\Facades\Route::has($action['route']) ? route($action['route']) : null);
                    if ($url) {
                        $defaultActions->push(['label' => $action['label'], 'url' => $url]);
                    }
                }
            }
        }
    }
    $combinedActions = $flashActions->merge($defaultActions);

    $lastBeneficiarioId = session('last_beneficiario_id');
    if ($lastBeneficiarioId) {
        $beneficiarioActions = collect([
            ['label' => 'Crear otro beneficiario', 'url' => route('beneficiarios.create')],
        ]);
        $user = auth()->user();
        if ($user && $user->hasRole('capturista') && \Illuminate\Support\Facades\Route::has('mis-registros.show')) {
            $beneficiarioActions->push([
                'label' => 'Ver mi registro',
                'url' => route('mis-registros.show', $lastBeneficiarioId),
            ]);
        } elseif (\Illuminate\Support\Facades\Route::has('beneficiarios.edit')) {
            $beneficiarioActions->push([
                'label' => 'Ver mi registro',
                'url' => route('beneficiarios.edit', $lastBeneficiarioId),
            ]);
        }
        $combinedActions = $beneficiarioActions->merge($combinedActions);
    }

    $combinedActions = $combinedActions->unique('url')->values();
    if ($success) {
        $toasts[] = ['msg' => $success, 'class' => 'text-bg-success', 'actions' => $combinedActions->toArray()];
    }
    if (session('error')) $toasts[] = ['msg' => session('error'), 'class' => 'text-bg-danger'];
    if (session('warning')) $toasts[] = ['msg' => session('warning'), 'class' => 'text-bg-warning'];
    if (session('info')) $toasts[] = ['msg' => session('info'), 'class' => 'text-bg-info'];
@endphp

@if(count($toasts))
<div aria-live="polite" aria-atomic="true" class="position-fixed top-0 end-0 p-3" style="z-index: 1080">
    @foreach($toasts as $i => $t)
        <div class="toast align-items-center {{ $t['class'] }} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-auto-show="true">
            <div class="d-flex">
                <div class="toast-body">
                    {{ $t['msg'] }}
                    @if(!empty($t['actions']))
                        <div class="mt-2 d-flex flex-wrap gap-2">
                            @foreach($t['actions'] as $action)
                                <a href="{{ $action['url'] }}" class="btn btn-sm btn-light">{{ $action['label'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
                <button type="button" class="btn-close @if(Str::contains($t['class'],'text-bg')) btn-close-white @endif me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    @endforeach
</div>
@endif
