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
        $capturistas = User::role(['capturista', 'capturista_programas'])->orderBy('name')->get(['uuid','name']);
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
        return $this->buildKpis($query, $request);
    }

    public function miProgresoKpis(Request $request)
    {
        $user = $request->user();
        $query = Beneficiario::where('created_by', $user->uuid);
        $filtered = clone $query;
        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from) {
            $filtered = $filtered->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $filtered = $filtered->whereDate('created_at', '<=', $to);
        }

        $now = Carbon::now();
        $startWeek = (clone $now)->startOfWeek();
        $start30 = (clone $now)->subDays(29)->startOfDay();
        $startToday = (clone $now)->startOfDay();

        $totalToday = (clone $filtered)->whereBetween('created_at', [$startToday, $now])->count();
        $totalWeek = (clone $filtered)->whereBetween('created_at', [$startWeek, $now])->count();
        $total30 = (clone $filtered)->whereBetween('created_at', [$start30, $now])->count();
        $ageRangeTotal = Beneficiario::whereBetween('edad', [18, 28])->count();

        $lastTen = (clone $filtered)->latest()->limit(10)->get(['id','folio_tarjeta','created_at']);
        $series = $this->dailySeries((clone $filtered)->whereBetween('created_at', [$start30, $now]), $start30, $now);

        return response()->json([
            'today' => $totalToday,
            'week' => $totalWeek,
            'last30Days' => $total30,
            'ageRange' => [
                'total' => $ageRangeTotal,
                'label' => '18-28',
            ],
            'ultimos' => $lastTen,
            'series' => $series,
        ]);
    }

    protected function applyFilters($query, Request $request, array $options = [])
    {
        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        $ignoreCapturista = $options['ignoreCapturista'] ?? false;
        return $query
            ->when($request->filled('municipio_id'), fn($q)=>$q->where('beneficiarios.municipio_id', $request->input('municipio_id')))
            ->when($request->filled('seccional'), fn($q)=>$q->whereHas('seccion', fn($sq)=>$sq->where('seccional','like','%'.$request->input('seccional').'%')))
            ->when(! $ignoreCapturista && $request->filled('capturista'), fn($q)=>$q->where('beneficiarios.created_by', $request->input('capturista')))
            ->when($from, fn($q)=>$q->whereDate('beneficiarios.created_at','>=', $from))
            ->when($to, fn($q)=>$q->whereDate('beneficiarios.created_at','<=', $to));
    }

    protected function buildKpis($baseQuery, Request $request)
    {
        $now = Carbon::now();
        $startWeek = (clone $now)->startOfWeek();
        $start30 = (clone $now)->subDays(29)->startOfDay();
        $startToday = (clone $now)->startOfDay();

        $total = (clone $baseQuery)->count();
        $ageRangeTotal = Beneficiario::whereBetween('edad', [18, 28])->count();

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
            ->join('secciones', 'beneficiarios.seccion_id', '=', 'secciones.id')
            ->selectRaw('secciones.seccional as seccional, COUNT(*) as c')
            ->groupBy('secciones.seccional')
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
        ];

        // Week daily series
        $weekSeries = $this->dailySeries((clone $baseQuery)->whereBetween('created_at', [$startWeek, $now]), $startWeek, $now);
        // Last 30 days daily series
        $last30Series = $this->dailySeries((clone $baseQuery)->whereBetween('created_at', [$start30, $now]), $start30, $now);

        return response()->json([
            'totals' => ['total' => $total],
            'ageRange' => [
                'total' => $ageRangeTotal,
                'label' => '18-28',
            ],
            'byMunicipio' => $byMunicipio,
            'bySeccional' => $bySeccional,
            'byCapturista' => $byCapturista,
            'today' => $today,
            'week' => $weekSeries,
            'last30Days' => $last30Series,
            'capturistasWeekBoard' => $this->weeklyCapturistaBoard($request),
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

    protected function weeklyCapturistaBoard(Request $request): array
    {
        $now = Carbon::now();
        $start = (clone $now)->startOfWeek();
        $weeks = [];
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = (clone $start)->subWeeks($i);
            $weeks[] = [
                'start' => $weekStart,
                'end' => (clone $weekStart)->endOfWeek(),
            ];
        }

        $capturistas = User::role(['capturista', 'capturista_programas'])
            ->orderBy('name')
            ->get(['uuid', 'name']);

        $base = $this->applyFilters(Beneficiario::query(), $request, ['ignoreCapturista' => true]);
        $countsByWeek = [];
        foreach ($weeks as $index => $range) {
            $countsByWeek[$index] = (clone $base)
                ->whereBetween('beneficiarios.created_at', [$range['start'], $range['end']])
                ->selectRaw('created_by, COUNT(*) as c')
                ->groupBy('created_by')
                ->pluck('c', 'created_by');
        }

        $rows = [];
        foreach ($capturistas as $capturista) {
            $counts = [];
            foreach ($countsByWeek as $weekly) {
                $counts[] = (int) ($weekly[$capturista->uuid] ?? 0);
            }
            $rows[] = [
                'uuid' => $capturista->uuid,
                'name' => $capturista->name,
                'counts' => $counts,
                'total' => array_sum($counts),
            ];
        }

        return [
            'labels' => array_map(fn ($week) => $week['start']->format('Y-m-d'), $weeks),
            'rows' => $rows,
        ];
    }
}
