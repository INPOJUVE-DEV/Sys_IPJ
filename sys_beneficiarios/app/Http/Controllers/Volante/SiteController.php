<?php

namespace App\Http\Controllers\Volante;

use App\Http\Controllers\Controller;
use App\Models\VolSite;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', VolSite::class);

        $sites = VolSite::query()
            ->when($request->boolean('with_trashed'), fn ($query) => $query->withTrashed())
            ->when($request->filled('active'), fn ($query) => $query->where('active', (bool) $request->boolean('active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json($sites);
    }

    public function store(Request $request)
    {
        $this->authorize('create', VolSite::class);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vol_sites', 'name')->whereNull('deleted_at'),
            ],
            'state' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $site = VolSite::create($data);

        return response()->json(['data' => $site], 201);
    }

    public function update(Request $request, VolSite $site)
    {
        $this->authorize('update', $site);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('vol_sites', 'name')
                    ->ignore($site->id)
                    ->whereNull('deleted_at'),
            ],
            'state' => ['sometimes', 'string', 'max:120'],
            'city' => ['sometimes', 'string', 'max:120'],
            'address' => ['sometimes', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $site->fill($data);
        $site->save();

        return response()->json(['data' => $site]);
    }

    public function destroy(VolSite $site)
    {
        $this->authorize('delete', $site);

        $site->delete();

        return response()->noContent();
    }
}