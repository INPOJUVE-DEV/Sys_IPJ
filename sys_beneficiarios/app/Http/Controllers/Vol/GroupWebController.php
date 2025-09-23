<?php

namespace App\Http\Controllers\Vol;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\VolGroup;
use App\Models\VolSite;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GroupWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', VolGroup::class);

        $query = VolGroup::query()
            ->withAvailability()
            ->with(['site'])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q');
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('start_date');

        $groups = $query->paginate(12)->withQueryString();

        $sites = VolSite::orderBy('name')->pluck('name', 'id');
        $filters = $request->only(['site_id', 'state', 'type', 'q']);

        return view('vol.groups.index', compact('groups', 'sites', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', VolGroup::class);

        return view('vol.groups.create_edit', [
            'sites' => VolSite::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', VolGroup::class);

        $data = $this->validatePayload($request, true);

        $group = DB::transaction(function () use ($data) {
            $programId = $this->resolveProgramId();
            $data['code'] = $this->nextGroupCode();
            $data['state'] = 'borrador';
            $data['capacity'] = $data['capacity'] ?? 12;
            $data['created_by'] = Auth::id();
            $data['program_id'] = $programId;
            $data['end_date'] = Carbon::parse($data['start_date'])->endOfMonth()->toDateString();

            return VolGroup::create($data);
        });

        return redirect()
            ->route('vol.groups.show', $group)
            ->with('status', 'Grupo creado correctamente.');
    }

    public function show(VolGroup $group, Request $request): View
    {
        $this->authorize('view', $group);

        $group->load(['site', 'program']);

        $enrollments = $group->enrollments()
            ->with(['beneficiario'])
            ->orderByDesc('enrolled_at')
            ->paginate(10)
            ->withQueryString();

        $activeCount = $group->enrollments()->where('status', 'inscrito')->count();

        return view('vol.groups.show', [
            'group' => $group,
            'enrollments' => $enrollments,
            'activeCount' => $activeCount,
        ]);
    }

    public function edit(VolGroup $group): View
    {
        $this->authorize('update', $group);

        return view('vol.groups.create_edit', [
            'group' => $group,
            'sites' => VolSite::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, VolGroup $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $data = $this->validatePayload($request, false, $group);
        $group->fill($data);
        $group->updated_by = Auth::id();
        if (array_key_exists('start_date', $data)) {
            $group->end_date = Carbon::parse($group->start_date)->endOfMonth()->toDateString();
        }
        $group->save();

        return redirect()
            ->route('vol.groups.show', $group)
            ->with('status', 'Grupo actualizado correctamente.');
    }

    public function publish(VolGroup $group): RedirectResponse
    {
        $this->authorize('update', $group);

        if (($group->capacity ?? 0) <= 0) {
            return back()->withErrors(['capacity' => 'Configura la capacidad antes de publicar.']);
        }

        $group->state = 'publicado';
        $group->updated_by = Auth::id();
        $group->save();

        return redirect()
            ->route('vol.groups.show', $group)
            ->with('status', 'Grupo publicado correctamente.');
    }

    public function close(VolGroup $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $group->state = 'cerrado';
        $group->updated_by = Auth::id();
        $group->save();

        return redirect()
            ->route('vol.groups.show', $group)
            ->with('status', 'Grupo cerrado correctamente.');
    }

    public function destroy(VolGroup $group): RedirectResponse
    {
        $this->authorize('delete', $group);
        $group->delete();

        return redirect()
            ->route('vol.groups.index')
            ->with('status', 'Grupo eliminado.');
    }

    private function validatePayload(Request $request, bool $isCreate, ?VolGroup $group = null): array
    {
        $rules = [
            'site_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:vol_sites,id'],
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'type' => [$isCreate ? 'required' : 'sometimes', 'in:semanal,sabatino'],
            'schedule_template' => [$isCreate ? 'required' : 'sometimes', 'in:lmv,mj,sab'],
            'start_date' => [$isCreate ? 'required' : 'sometimes', 'date'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'state' => ['sometimes', 'in:borrador,publicado,cerrado'],
        ];

        return Validator::make($request->all(), $rules)->validate();
    }

    private function nextGroupCode(): string
    {
        $now = now();
        $sequence = VolGroup::withTrashed()
            ->whereYear('created_at', $now->year)
            ->count() + 1;

        return sprintf('JAV-%s%s', $now->format('y'), str_pad((string) $sequence, 3, '0', STR_PAD_LEFT));
    }

    private function resolveProgramId(): int
    {
        $program = Program::where('slug', 'jovenes-al-volante')
            ->orWhere('slug', 'ja3venes-al-volante')
            ->orWhere('name', 'like', '%volante%')
            ->first();

        if (! $program) {
            $program = Program::create([
                'name' => 'Jovenes al Volante',
                'slug' => 'jovenes-al-volante',
                'area' => 'Bienestar',
                'active' => true,
                'description' => 'Programa Jovenes al Volante',
            ]);
        }

        return (int) $program->id;
    }
}

