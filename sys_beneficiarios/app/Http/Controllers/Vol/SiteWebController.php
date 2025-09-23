<?php

namespace App\Http\Controllers\Vol;

use App\Http\Controllers\Controller;
use App\Models\VolSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', VolSite::class);

        $sites = VolSite::withTrashed()
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('vol.sites.index', compact('sites'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', VolSite::class);

        $data = $this->validatePayload($request);
        VolSite::create($data);

        return redirect()
            ->route('vol.sites.index')
            ->with('status', 'Sede creada correctamente.');
    }

    public function update(Request $request, VolSite $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $data = $this->validatePayload($request, false);
        $site->fill($data);
        $site->active = $request->boolean('active');
        $site->save();

        return redirect()
            ->route('vol.sites.index')
            ->with('status', 'Sede actualizada correctamente.');
    }

    public function destroy(VolSite $site): RedirectResponse
    {
        $this->authorize('delete', $site);

        $site->delete();

        return redirect()
            ->route('vol.sites.index')
            ->with('status', 'Sede eliminada.');
    }

    private function validatePayload(Request $request, bool $requireActive = true): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'active' => [$requireActive ? 'required' : 'nullable'],
        ]);

        $data['active'] = $request->boolean('active');

        return $data;
    }
}

