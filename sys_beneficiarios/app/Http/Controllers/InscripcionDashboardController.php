<?php

namespace App\Http\Controllers;

use App\Models\Inscripcion;
use App\Models\Programa;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InscripcionDashboardController extends Controller
{
    public function index()
    {
        $programas = Programa::orderBy('nombre')->get(['id', 'nombre']);
        return view('inscripciones.dashboard', compact('programas'));
    }

    public function kpis(Request $request)
    {
        $programaId = $request->input('programa_id');
        $now = Carbon::now();
        $start = $this->parseMonth($request->input('from')) ?? (clone $now)->subMonths(11)->startOfMonth();
        $end = $this->parseMonth($request->input('to')) ?? (clone $now)->endOfMonth();

        $start = (clone $start)->startOfMonth();
        $end = (clone $end)->endOfMonth();
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $baseQuery = Inscripcion::query()
            ->when($programaId, fn ($q) => $q->where('programa_id', $programaId));

        $startKey = $start->format('Y-m');
        $endKey = $end->format('Y-m');

        $total = (clone $baseQuery)->whereBetween('periodo', [$startKey, $endKey])->count();

        $monthRows = (clone $baseQuery)
            ->whereBetween('periodo', [$startKey, $endKey])
            ->selectRaw('periodo, COUNT(*) as c')
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->pluck('c', 'periodo');

        $labels = [];
        $data = [];
        $cursor = (clone $start)->startOfMonth();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $labels[] = $key;
            $data[] = (int) ($monthRows[$key] ?? 0);
            $cursor->addMonth();
        }

        $byProgram = (clone $baseQuery)
            ->whereBetween('periodo', [$startKey, $endKey])
            ->selectRaw('programa_id, COUNT(*) as c')
            ->groupBy('programa_id')
            ->orderByDesc('c')
            ->get();
        $programaNames = Programa::whereIn('id', $byProgram->pluck('programa_id'))
            ->pluck('nombre', 'id');
        $byPrograma = [
            'labels' => $byProgram->pluck('programa_id')->map(fn ($id) => $programaNames[$id] ?? $id)->all(),
            'data' => $byProgram->pluck('c')->map(fn ($v) => (int) $v)->all(),
        ];

        $currentKey = $now->format('Y-m');
        $currentMonth = (clone $baseQuery)->where('periodo', $currentKey)->count();

        return response()->json([
            'totals' => [
                'total' => $total,
                'currentMonth' => $currentMonth,
            ],
            'monthly' => [
                'labels' => $labels,
                'data' => $data,
                'total' => array_sum($data),
            ],
            'byPrograma' => $byPrograma,
            'range' => [
                'from' => $start->format('Y-m'),
                'to' => $end->format('Y-m'),
            ],
        ]);
    }

    protected function parseMonth(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m', $value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
