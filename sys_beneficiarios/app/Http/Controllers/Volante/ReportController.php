<?php

namespace App\Http\Controllers\Volante;

use App\Http\Controllers\Controller;
use App\Models\VolEnrollment;
use App\Models\VolGroup;
use App\Models\VolPayment;
use App\Models\VolSite;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function monthly(Request $request)
    {
        $this->authorizeReports($request);

        $validated = Validator::make($request->all(), [
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'site_id' => ['nullable', 'integer', 'exists:vol_sites,id'],
        ])->validate();

        $monthInput = $validated['month'] ?? now()->format('Y-m');
        $siteId = $validated['site_id'] ?? null;
        $monthStart = $this->resolveMonth($monthInput);

        $data = $this->buildMonthlyData($monthStart, $siteId);

        return response()->json($data);
    }

    public function quarterly(Request $request)
    {
        $this->authorizeReports($request);

        $validated = Validator::make($request->all(), [
            'year' => ['nullable', 'digits:4'],
            'q' => ['nullable', 'integer', 'between:1,4'],
            'site_id' => ['nullable', 'integer', 'exists:vol_sites,id'],
        ])->validate();

        $year = isset($validated['year']) ? (int) $validated['year'] : (int) now()->year;
        $quarter = isset($validated['q']) ? (int) $validated['q'] : (int) ceil(now()->month / 3);
        $siteId = $validated['site_id'] ?? null;

        $quarterData = $this->buildQuarterlyData($year, $quarter, $siteId);

        return response()->json($quarterData);
    }

    public function availability(Request $request)
    {
        $this->authorizeReports($request);

        $validated = Validator::make($request->all(), [
            'site_id' => ['nullable', 'integer', 'exists:vol_sites,id'],
        ])->validate();

        $siteId = $validated['site_id'] ?? null;
        $availability = $this->buildAvailabilityData($siteId);

        return response()->json($availability);
    }

    public function dashboard(Request $request)
    {
        $this->authorizeReports($request);

        $validated = Validator::make($request->all(), [
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'site_id' => ['nullable', 'integer', 'exists:vol_sites,id'],
            'year' => ['nullable', 'digits:4'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
        ])->validate();

        $monthInput = $validated['month'] ?? now()->format('Y-m');
        $siteId = $validated['site_id'] ?? null;
        $monthStart = $this->resolveMonth($monthInput);

        $year = isset($validated['year']) ? (int) $validated['year'] : (int) $monthStart->year;
        $quarter = isset($validated['quarter']) ? (int) $validated['quarter'] : (int) ceil($monthStart->month / 3);

        $monthly = $this->buildMonthlyData($monthStart, $siteId);
        $quarterly = $this->buildQuarterlyData($year, $quarter, $siteId);
        $availability = $this->buildAvailabilityData($siteId);
        $payments = $this->buildPaymentStats($monthStart, $siteId);

        $sites = VolSite::query()->orderBy('name')->pluck('name', 'id');

        return view('vol.dashboard.index', [
            'monthly' => $monthly,
            'quarterly' => $quarterly,
            'availability' => $availability,
            'payments' => $payments,
            'sites' => $sites,
            'filters' => [
                'month' => $monthStart->format('Y-m'),
                'site_id' => $siteId,
                'year' => $quarterly['year'],
                'quarter' => $quarterly['quarter'],
            ],
            'copy' => $this->dashboardCopy(),
        ]);
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless($request->user()?->can('vol.reports.view'), 403);
    }

    private function resolveMonth(string $month): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        } catch (\Exception $e) {
            abort(422, 'Formato de mes no valido.');
        }
    }

    private function buildMonthlyData(Carbon $monthStart, ?int $siteId = null): array
    {
        $start = $monthStart->copy()->startOfMonth();
        $base = VolEnrollment::query()->active()->monthly($monthStart);

        if ($siteId) {
            $base->whereHas('group', fn ($query) => $query->where('site_id', $siteId));
        }

        $total = (clone $base)->count();

        $perSite = $this->aggregateBySite($start, $start->copy()->endOfMonth(), $siteId);
        $perGroup = $this->aggregateByGroup($start, $start->copy()->endOfMonth(), $siteId);

        $beneficiaries = VolEnrollment::query()
            ->join('beneficiarios', 'beneficiarios.id', '=', 'vol_enrollments.beneficiario_id')
            ->join('vol_groups', 'vol_groups.id', '=', 'vol_enrollments.group_id')
            ->join('vol_sites', 'vol_sites.id', '=', 'vol_groups.site_id')
            ->monthly($monthStart)
            ->where('vol_enrollments.status', 'inscrito')
            ->when($siteId, fn ($query) => $query->where('vol_groups.site_id', $siteId))
            ->orderByDesc('vol_enrollments.enrolled_at')
            ->limit(200)
            ->get([
                'beneficiarios.id as beneficiario_id',
                'beneficiarios.nombre',
                'beneficiarios.apellido_paterno',
                'beneficiarios.apellido_materno',
                'vol_groups.id as group_id',
                'vol_groups.name as group_name',
                'vol_sites.id as site_id',
                'vol_sites.name as site_name',
                'vol_enrollments.enrolled_at',
            ])
            ->map(fn ($row) => [
                'beneficiario_id' => $row->beneficiario_id,
                'nombre' => trim(sprintf('%s %s %s', $row->nombre, $row->apellido_paterno, $row->apellido_materno)),
                'group_id' => $row->group_id,
                'group_name' => $row->group_name,
                'site_id' => $row->site_id,
                'site_name' => $row->site_name,
                'enrolled_at' => optional($row->enrolled_at)->toDateTimeString(),
            ])
            ->toArray();

        return [
            'period' => $start->format('Y-m'),
            'start' => $start->toDateString(),
            'end' => $start->copy()->endOfMonth()->toDateString(),
            'site_id' => $siteId,
            'total' => $total,
            'per_site' => $perSite,
            'per_group' => $perGroup,
            'beneficiaries' => $beneficiaries,
        ];
    }

    private function buildQuarterlyData(int $year, int $quarter, ?int $siteId = null): array
    {
        $quarter = max(1, min(4, $quarter));
        $startMonth = (($quarter - 1) * 3) + 1;
        $start = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $end = $start->copy()->addMonths(2)->endOfMonth();

        $base = VolEnrollment::query()->active()->quarter($start->year, $quarter);

        if ($siteId) {
            $base->whereHas('group', fn ($query) => $query->where('site_id', $siteId));
        }

        $total = (clone $base)->count();

        $dateFormat = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', enrolled_at)"
            : "DATE_FORMAT(enrolled_at, '%Y-%m')";

        $perMonth = (clone $base)
            ->select(DB::raw("$dateFormat as period"), DB::raw('COUNT(*) as total'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'total' => (int) $row->total,
            ])
            ->toArray();

        return [
            'year' => $start->year,
            'quarter' => $quarter,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'site_id' => $siteId,
            'total' => $total,
            'per_site' => $this->aggregateBySite($start, $end, $siteId),
            'per_group' => $this->aggregateByGroup($start, $end, $siteId),
            'per_month' => $perMonth,
        ];
    }

    private function buildAvailabilityData(?int $siteId = null): array
    {
        $groups = VolGroup::withAvailability()
            ->with('site:id,name')
            ->when($siteId, fn ($query) => $query->where('site_id', $siteId))
            ->orderBy('site_id')
            ->orderBy('name')
            ->get()
            ->filter(fn (VolGroup $group) => ($group->available_slots ?? 0) > 0)
            ->map(fn (VolGroup $group) => [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'code' => $group->code,
                'site_id' => $group->site_id,
                'site_name' => optional($group->site)->name,
                'capacity' => (int) $group->capacity,
                'active' => (int) ($group->active_enrollments ?? 0),
                'available' => (int) $group->available_slots,
            ])
            ->values()
            ->toArray();

        $totals = [
            'capacity' => array_sum(array_column($groups, 'capacity')),
            'active' => array_sum(array_column($groups, 'active')),
        ];
        $totals['available'] = max(0, $totals['capacity'] - $totals['active']);

        return [
            'site_id' => $siteId,
            'groups' => $groups,
            'totals' => $totals,
        ];
    }

    private function buildPaymentStats(Carbon $monthStart, ?int $siteId = null): array
    {
        $start = $monthStart->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $beneficiarioIds = VolEnrollment::query()
            ->active()
            ->withinEnrollmentRange($start, $end)
            ->join('vol_groups', 'vol_groups.id', '=', 'vol_enrollments.group_id')
            ->when($siteId, fn ($query) => $query->where('vol_groups.site_id', $siteId))
            ->pluck('vol_enrollments.beneficiario_id')
            ->unique()
            ->values();

        if ($beneficiarioIds->isEmpty()) {
            return [
                'period' => $start->format('Y-m'),
                'total' => 0,
                'paid' => 0,
                'pending' => 0,
            ];
        }

        $paid = VolPayment::query()
            ->whereIn('beneficiario_id', $beneficiarioIds)
            ->whereNotNull('payment_date')
            ->distinct('beneficiario_id')
            ->count('beneficiario_id');

        $total = $beneficiarioIds->count();
        $pending = max(0, $total - $paid);

        return [
            'period' => $start->format('Y-m'),
            'total' => $total,
            'paid' => $paid,
            'pending' => $pending,
        ];
    }

    private function aggregateBySite(Carbon $start, Carbon $end, ?int $siteId = null): array
    {
        $referenceDate = $end->toDateString();

        $minorsTotalExpression = "SUM(CASE WHEN beneficiarios.fecha_nacimiento IS NOT NULL AND TIMESTAMPDIFF(YEAR, beneficiarios.fecha_nacimiento, ?) < 18 THEN 1 ELSE 0 END)";
        $adultsTotalExpression = "SUM(CASE WHEN beneficiarios.fecha_nacimiento IS NOT NULL AND TIMESTAMPDIFF(YEAR, beneficiarios.fecha_nacimiento, ?) >= 18 THEN 1 ELSE 0 END)";
        $bindings = [$referenceDate, $referenceDate];

        if (DB::connection()->getDriverName() === 'sqlite') {
            $minorsTotalExpression = "SUM(CASE WHEN beneficiarios.fecha_nacimiento IS NOT NULL AND (strftime('%Y', ?) - strftime('%Y', beneficiarios.fecha_nacimiento)) < 18 THEN 1 ELSE 0 END)";
            $adultsTotalExpression = "SUM(CASE WHEN beneficiarios.fecha_nacimiento IS NOT NULL AND (strftime('%Y', ?) - strftime('%Y', beneficiarios.fecha_nacimiento)) >= 18 THEN 1 ELSE 0 END)";
            $bindings = [$referenceDate, $referenceDate];
        }


        return DB::table('vol_enrollments')
            ->join('vol_groups', 'vol_groups.id', '=', 'vol_enrollments.group_id')
            ->join('vol_sites', 'vol_sites.id', '=', 'vol_groups.site_id')
            ->join('beneficiarios', 'beneficiarios.id', '=', 'vol_enrollments.beneficiario_id')
            ->where('vol_enrollments.status', 'inscrito')
            ->whereBetween('vol_enrollments.enrolled_at', [$start, $end])
            ->when($siteId, fn ($query) => $query->where('vol_groups.site_id', $siteId))
            ->selectRaw(
                "vol_sites.id as site_id, " .
                "vol_sites.name as site_name, " .
                "COUNT(*) as total, " .
                "SUM(CASE WHEN UPPER(beneficiarios.sexo) = 'M' THEN 1 ELSE 0 END) as male_total, " .
                "SUM(CASE WHEN UPPER(beneficiarios.sexo) = 'F' THEN 1 ELSE 0 END) as female_total, " .
                "$minorsTotalExpression as minors_total, " .
                "$adultsTotalExpression as adults_total",
                $bindings
            )
            ->groupBy('vol_sites.id', 'vol_sites.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'site_id' => (int) $row->site_id,
                'site_name' => $row->site_name,
                'total' => (int) $row->total,
                'male' => (int) $row->male_total,
                'female' => (int) $row->female_total,
                'minors' => (int) $row->minors_total,
                'adults' => (int) $row->adults_total,
            ])
            ->toArray();
    }

    private function aggregateByGroup(Carbon $start, Carbon $end, ?int $siteId = null): array
    {
        return DB::table('vol_enrollments')
            ->join('vol_groups', 'vol_groups.id', '=', 'vol_enrollments.group_id')
            ->leftJoin('vol_sites', 'vol_sites.id', '=', 'vol_groups.site_id')
            ->where('vol_enrollments.status', 'inscrito')
            ->whereBetween('vol_enrollments.enrolled_at', [$start, $end])
            ->when($siteId, fn ($query) => $query->where('vol_groups.site_id', $siteId))
            ->select(
                'vol_groups.id as group_id',
                'vol_groups.name as group_name',
                'vol_groups.code',
                'vol_sites.name as site_name',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('vol_groups.id', 'vol_groups.name', 'vol_groups.code', 'vol_sites.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'group_id' => (int) $row->group_id,
                'group_name' => $row->group_name,
                'code' => $row->code,
                'site_name' => $row->site_name,
                'total' => (int) $row->total,
            ])
            ->toArray();
    }

    private function dashboardCopy(): array
    {
        return [
            'message' => 'Los grupos operan con plantillas semanales (LMV, MJ, Sab) y periodos definidos. Usa estos indicadores para validar capacidad, disponibilidad y ritmo de inscripciones.',
        ];
    }
}
