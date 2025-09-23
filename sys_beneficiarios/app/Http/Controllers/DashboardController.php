<?php

namespace App\Http\Controllers;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Views
    public function admin()
    {
        $municipios = Municipio::orderBy('nombre')->pluck('nombre','id');
        $capturistas = User::role('capturista')->orderBy('name')->get(['uuid','name']);
        return view('roles.admin', compact('municipios','capturistas'));
    }

    public function capturista()
    {
        return view('roles.capturista');
    }

    // KPIs
    public function adminKpis(Request $request)
    {
        $query = $this->applyFilters(Beneficiario::query(), $request);
        return $this->buildKpis($query);
    }

    public function miProgresoKpis(Request $request)
    {
        $user = $request->user();
        $query = Beneficiario::where('created_by', $user->uuid);
        $filtered = clone $query;
        if ($request->filled('from')) {
            $filtered = $filtered->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $filtered = $filtered->whereDate('created_at', '<=', $request->date('to'));
        }

        $now = Carbon::now();
        $startWeek = (clone $now)->startOfWeek();
        $start30 = (clone $now)->subDays(29)->startOfDay();
        $startToday = (clone $now)->startOfDay();

        $totalToday = (clone $filtered)->whereBetween('created_at', [$startToday, $now])->count();
        $totalWeek = (clone $filtered)->whereBetween('created_at', [$startWeek, $now])->count();
        $total30 = (clone $filtered)->whereBetween('created_at', [$start30, $now])->count();

        $drafts = (clone $filtered)->where('is_draft', true)->count();
        $regs = (clone $filtered)->where('is_draft', false)->count();

        $lastTen = (clone $filtered)->latest()->limit(10)->get(['id','folio_tarjeta','created_at','is_draft']);
        $series = $this->dailySeries((clone $filtered)->whereBetween('created_at', [$start30, $now]), $start30, $now);

        return response()->json([
            'today' => $totalToday,
            'week' => $totalWeek,
            'last30Days' => $total30,
            'estado' => [
                'labels' => ['Borrador', 'Registrado'],
                'data' => [$drafts, $regs],
            ],
            'ultimos' => $lastTen,
            'series' => $series,
        ]);
    }

    protected function applyFilters($query, Request $request)
    {
        return $query
            ->when($request->filled('municipio_id'), fn($q)=>$q->where('municipio_id', $request->input('municipio_id')))
            ->when($request->filled('seccional'), fn($q)=>$q->where('seccional','like','%'.$request->input('seccional').'%'))
            ->when($request->filled('capturista'), fn($q)=>$q->where('created_by', $request->input('capturista')))
            ->when($request->filled('estado'), function ($q) use ($request) {
                return $request->input('estado') === 'borrador' ? $q->where('is_draft', true) : ($request->input('estado')==='registrado' ? $q->where('is_draft', false) : $q);
            })
            ->when($request->filled('from'), fn($q)=>$q->whereDate('created_at','>=', $request->date('from')))
            ->when($request->filled('to'), fn($q)=>$q->whereDate('created_at','<=', $request->date('to')));
    }

    protected function buildKpis($baseQuery)
    {
        $now = Carbon::now();
        $startWeek = (clone $now)->startOfWeek();
        $start30 = (clone $now)->subDays(29)->startOfDay();
        $startToday = (clone $now)->startOfDay();

        $total = (clone $baseQuery)->count();
        $borrador = (clone $baseQuery)->where('is_draft', true)->count();
        $registrado = (clone $baseQuery)->where('is_draft', false)->count();

        // By Municipio
        $byMun = (clone $baseQuery)
            ->selectRaw('municipio_id, COUNT(*) as c')
            ->groupBy('municipio_id')
            ->pluck('c', 'municipio_id');
        $munNames = Municipio::whereIn('id', $byMun->keys())->pluck('nombre', 'id');
        $byMunicipio = [
            'labels' => $byMun->keys()->map(fn($id) => $munNames[$id] ?? 'N/A')->values()->all(),
            'data' => $byMun->values()->all(),
        ];

        // By Seccional (top 10)
        $bySec = (clone $baseQuery)
            ->selectRaw('seccional, COUNT(*) as c')
            ->groupBy('seccional')
            ->orderByDesc('c')
            ->limit(10)
            ->get();
        $bySeccional = [
            'labels' => $bySec->pluck('seccional')->all(),
            'data' => $bySec->pluck('c')->all(),
        ];

        // By Capturista (top 10)
        $byCap = (clone $baseQuery)
            ->selectRaw('created_by, COUNT(*) as c')
            ->groupBy('created_by')
            ->orderByDesc('c')
            ->limit(10)
            ->get();
        $names = User::whereIn('uuid', $byCap->pluck('created_by'))->pluck('name', 'uuid');
        $byCapturista = [
            'labels' => $byCap->pluck('created_by')->map(fn($u) => $names[$u] ?? $u)->all(),
            'data' => $byCap->pluck('c')->all(),
        ];

        // Today
        $todayTotal = (clone $baseQuery)->whereBetween('created_at', [$startToday, $now])->count();
        $today = [
            'total' => $todayTotal,
            'borrador' => (clone $baseQuery)->where('is_draft', true)->whereBetween('created_at', [$startToday, $now])->count(),
            'registrado' => (clone $baseQuery)->where('is_draft', false)->whereBetween('created_at', [$startToday, $now])->count(),
        ];

        // Week daily series
        $weekSeries = $this->dailySeries((clone $baseQuery)->whereBetween('created_at', [$startWeek, $now]), $startWeek, $now);
        // Last 30 days daily series
        $last30Series = $this->dailySeries((clone $baseQuery)->whereBetween('created_at', [$start30, $now]), $start30, $now);

        return response()->json([
            'totals' => ['total' => $total, 'borrador' => $borrador, 'registrado' => $registrado],
            'byMunicipio' => $byMunicipio,
            'bySeccional' => $bySeccional,
            'byCapturista' => $byCapturista,
            'today' => $today,
            'week' => $weekSeries,
            'last30Days' => $last30Series,
        ]);
    }

    protected function dailySeries($query, Carbon $start, Carbon $end)
    {
        $rows = $query
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd');

        $labels = [];
        $data = [];
        $cursor = (clone $start)->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('Y-m-d');
            $data[] = (int) ($rows[$key] ?? 0);
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'total' => array_sum($data),
        ];
    }
}
