<?php

namespace App\Http\Controllers\Volante;

use App\Http\Controllers\Controller;
use App\Models\VolGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', VolGroup::class);

        $groups = VolGroup::query()
            ->with(['site'])
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', $request->input('site_id')))
            ->when($request->filled('state'), fn ($query) => $query->where('state', $request->input('state')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $value = $request->input('q');
                $query->where(function ($inner) use ($value) {
                    $inner->where('name', 'like', "%$value%")
                        ->orWhere('code', 'like', "%$value%");
                });
            })
            ->when($request->boolean('with_trashed'), fn ($query) => $query->withTrashed())
            ->orderByDesc('start_date')
            ->paginate($request->integer('per_page', 15));

        return response()->json($groups);
    }

    public function show(VolGroup $group)
    {
        $this->authorize('view', $group);

        return response()->json(['data' => $group->load(['site', 'enrollments'])]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', VolGroup::class);

        $data = $request->validate([
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'site_id' => ['required', 'integer', 'exists:vol_sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['semanal', 'sabatino'])],
            'schedule_template' => ['required', Rule::in(['lmv', 'mj', 'sab'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'created_by' => ['sometimes', 'integer'],
        ]);

        $code = $this->nextGroupCode();

        $data['code'] = $code;
        $data['state'] = 'borrador';
        $data['capacity'] = $data['capacity'] ?? 12;
        $data['created_by'] = $data['created_by'] ?? Auth::id();

        $group = VolGroup::create($data);

        return response()->json(['data' => $group->fresh(['site'])], 201);
    }

    public function update(Request $request, VolGroup $group)
    {
        $this->authorize('update', $group);

        $data = $request->validate([
            'program_id' => ['sometimes', 'integer', 'exists:programs,id'],
            'site_id' => ['sometimes', 'integer', 'exists:vol_sites,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['semanal', 'sabatino'])],
            'schedule_template' => ['sometimes', Rule::in(['lmv', 'mj', 'sab'])],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'capacity' => ['sometimes', 'integer', 'min:0'],
            'state' => ['sometimes', Rule::in(['borrador', 'publicado', 'cerrado'])],
            'updated_by' => ['sometimes', 'integer'],
        ]);

        if (array_key_exists('end_date', $data)) {
            $startReference = $data['start_date'] ?? $group->start_date;
            if (! empty($data['end_date']) && $startReference && Carbon::parse($data['end_date'])->lt(Carbon::parse($startReference))) {
                throw ValidationException::withMessages([
                    'end_date' => ['La fecha de cierre debe ser posterior o igual a la fecha de inicio.'],
                ]);
            }
        }

        $group->fill($data);
        $group->updated_by = $data['updated_by'] ?? Auth::id();
        $group->save();

        return response()->json(['data' => $group->fresh(['site'])]);
    }

    public function publish(VolGroup $group)
    {
        $this->authorize('update', $group);

        if (($group->capacity ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'capacity' => ['No se puede publicar un grupo sin cupo disponible.'],
            ]);
        }

        $group->state = 'publicado';
        $group->updated_by = Auth::id();
        $group->save();

        return response()->json(['data' => $group]);
    }

    public function close(VolGroup $group)
    {
        $this->authorize('update', $group);

        $group->state = 'cerrado';
        $group->updated_by = Auth::id();
        $group->save();

        return response()->json(['data' => $group]);
    }

    public function destroy(VolGroup $group)
    {
        $this->authorize('delete', $group);

        $group->delete();

        return response()->noContent();
    }

    private function nextGroupCode(): string
    {
        $now = Carbon::now();
        $sequence = VolGroup::withTrashed()
            ->whereYear('created_at', $now->year)
            ->count() + 1;

        return sprintf('JAV-%s%s', $now->format('y'), str_pad($sequence, 3, '0', STR_PAD_LEFT));
    }
}