<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBeneficiarioRequest;
use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Models\Municipio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\SeccionResolver;

class MisRegistrosController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Beneficiario::class);
        $baseQuery = Beneficiario::with(['municipio','seccion'])
            ->where('created_by', $request->user()->uuid);

        $items = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $month = $this->resolveMonth($request->input('month'));
        $monthStart = (clone $month)->startOfMonth();
        $monthEnd = (clone $month)->endOfMonth();
        $monthQuery = (clone $baseQuery)->whereBetween('created_at', [$monthStart, $monthEnd]);

        $totalMonth = (clone $monthQuery)->count();
        $genderCounts = (clone $monthQuery)
            ->whereIn('sexo', ['M', 'F'])
            ->selectRaw('sexo, COUNT(*) as c')
            ->groupBy('sexo')
            ->pluck('c', 'sexo');
        $maleCount = (int) ($genderCounts['M'] ?? 0);
        $femaleCount = (int) ($genderCounts['F'] ?? 0);

        $ageRangeCount = (clone $monthQuery)
            ->whereBetween('edad', [17, 25])
            ->count();

        $year = (int) $month->format('Y');
        $monthLabels = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];
        $monthlyRows = (clone $baseQuery)
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as m, COUNT(*) as c')
            ->groupBy('m')
            ->pluck('c', 'm');
        $monthlyCounts = [];
        foreach ($monthLabels as $m => $label) {
            $monthlyCounts[] = [
                'label' => $label,
                'count' => (int) ($monthlyRows[$m] ?? 0),
            ];
        }

        return view('mis_registros.index', compact(
            'items',
            'month',
            'totalMonth',
            'maleCount',
            'femaleCount',
            'ageRangeCount',
            'monthlyCounts',
            'year'
        ));
    }

    public function show(Beneficiario $beneficiario)
    {
        $this->authorize('view', $beneficiario);
        $activities = Activity::forSubject($beneficiario)->latest()->limit(10)->get();
        return view('mis_registros.show', compact('beneficiario','activities'));
    }

    public function edit(Beneficiario $beneficiario)
    {
        $this->authorize('update', $beneficiario);
        $municipios = Municipio::orderBy('nombre')->pluck('nombre','id');
        $domicilio = $beneficiario->domicilio;
        return view('mis_registros.edit', compact('beneficiario','municipios','domicilio'));
    }

    public function update(UpdateBeneficiarioRequest $request, Beneficiario $beneficiario)
    {
        $this->authorize('update', $beneficiario);
        $data = $request->validated();
        $dom = $data['domicilio'] ?? [];
        $seccion = SeccionResolver::resolve($dom['seccional'] ?? null);
        if (! $seccion) {
            throw ValidationException::withMessages([
                'domicilio.seccional' => 'La seccional no se encuentra en el catálogo.',
            ]);
        }

        $beneficiario->fill($data);
        $beneficiario->seccion()->associate($seccion);
        $beneficiario->municipio_id = $dom['municipio_id'] ?? $seccion->municipio_id;
        $beneficiario->save();

        $d = $beneficiario->domicilio ?: new Domicilio([
            'id' => (string) Str::uuid(),
            'beneficiario_id' => $beneficiario->id,
        ]);

        $d->fill([
            'calle' => $dom['calle'] ?? '',
            'numero_ext' => $dom['numero_ext'] ?? '',
            'numero_int' => $dom['numero_int'] ?? null,
            'colonia' => $dom['colonia'] ?? '',
            'municipio_id' => $dom['municipio_id'] ?? $seccion->municipio_id,
            'codigo_postal' => $dom['codigo_postal'] ?? '',
            'seccion_id' => $seccion->id,
        ])->save();

        return redirect()->route('mis-registros.show', $beneficiario)->with('status', 'Actualizado correctamente');
    }

    private function resolveMonth(?string $value): Carbon
    {
        if ($value) {
            try {
                return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
            } catch (\Throwable $e) {
                // Fall through to now.
            }
        }

        return Carbon::now()->startOfMonth();
    }
}
