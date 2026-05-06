<?php

namespace App\Http\Controllers\Delegacion;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Evento;
use App\Models\Inscripcion;
use App\Models\Tarjeta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $officeId = $user->oficina_id;

        $now = Carbon::now();
        $monthStart = (clone $now)->startOfMonth();
        $todayStart = (clone $now)->startOfDay();
        $periodoActual = $now->format('Y-m');
        $edadObjetivoMin = 17;
        $edadObjetivoMax = 28;

        $eventosBase = $this->officeEventosQuery($officeId);
        $inscripcionesBase = $this->officeInscripcionesQuery($officeId);
        $beneficiariosBase = $this->officeBeneficiariosQuery($officeId);

        $eventosSummary = [
            'total' => (clone $eventosBase)->count(),
            'mes' => (clone $eventosBase)->whereBetween('created_at', [$monthStart, $now])->count(),
            'hoy' => (clone $eventosBase)->whereBetween('created_at', [$todayStart, $now])->count(),
            'asistentes' => (int) (clone $eventosBase)->sum('total_asistentes'),
            'anfitrion' => (clone $eventosBase)->where('rol_participacion', Evento::ROL_ANFITRION)->count(),
            'invitado' => (clone $eventosBase)->where('rol_participacion', Evento::ROL_INVITADO)->count(),
        ];

        $eventosPorTipo = (clone $eventosBase)
            ->with('tipo:id,nombre')
            ->select([
                'evento_tipo_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(total_asistentes) as asistentes'),
            ])
            ->groupBy('evento_tipo_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $inscripcionesSummary = [
            'total' => (clone $inscripcionesBase)->count(),
            'periodo' => (clone $inscripcionesBase)->where('periodo', $periodoActual)->count(),
            'mes' => (clone $inscripcionesBase)->whereBetween('created_at', [$monthStart, $now])->count(),
            'activas' => (clone $inscripcionesBase)->where('estatus', 'inscrito')->count(),
        ];

        $inscripcionesPorPrograma = (clone $inscripcionesBase)
            ->with('programa:id,nombre')
            ->where('periodo', $periodoActual)
            ->select([
                'programa_id',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('programa_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $tarjetasSummary = [
            'total' => $this->officeTarjetasQuery($officeId)->count(),
            'listas' => $this->officeTarjetasQuery($officeId)
                ->whereNull('municipio_id')
                ->count(),
            'municipio' => $this->officeTarjetasQuery($officeId)
                ->whereNotNull('municipio_id')
                ->count(),
            'capturadas' => (clone $beneficiariosBase)->count(),
        ];

        $capturadasEdadObjetivo = (clone $beneficiariosBase)
            ->whereBetween('edad', [$edadObjetivoMin, $edadObjetivoMax])
            ->count();

        $capturadasMenores = (clone $beneficiariosBase)
            ->where('edad', '<', $edadObjetivoMin)
            ->count();

        $capturadasMayores = (clone $beneficiariosBase)
            ->where('edad', '>', $edadObjetivoMax)
            ->count();

        $capturadasPorSexo = (clone $beneficiariosBase)
            ->whereBetween('edad', [$edadObjetivoMin, $edadObjetivoMax])
            ->select([
                'sexo',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('sexo')
            ->pluck('total', 'sexo');

        $capturadasSummary = [
            'total' => (clone $beneficiariosBase)->count(),
            'mes' => (clone $beneficiariosBase)->whereBetween('created_at', [$monthStart, $now])->count(),
            'hoy' => (clone $beneficiariosBase)->whereBetween('created_at', [$todayStart, $now])->count(),
            'edad_objetivo' => $capturadasEdadObjetivo,
            'menores' => $capturadasMenores,
            'mayores' => $capturadasMayores,
            'mujeres_objetivo' => (int) ($capturadasPorSexo['F'] ?? 0),
            'hombres_objetivo' => (int) ($capturadasPorSexo['M'] ?? 0),
            'otro_objetivo' => (int) ($capturadasPorSexo['X'] ?? 0),
        ];

        $tarjetasAsignadasPorMunicipio = Tarjeta::query()
            ->select('municipio_id', DB::raw('COUNT(*) as asignadas'))
            ->where('oficina_id', $officeId)
            ->whereNotNull('municipio_id')
            ->groupBy('municipio_id');

        $beneficiariosPorMunicipio = Beneficiario::query()
            ->select('municipio_id')
            ->selectRaw('COUNT(*) as capturadas')
            ->selectRaw('SUM(CASE WHEN edad BETWEEN ? AND ? THEN 1 ELSE 0 END) as edad_objetivo', [$edadObjetivoMin, $edadObjetivoMax])
            ->selectRaw('SUM(CASE WHEN edad < ? THEN 1 ELSE 0 END) as menores', [$edadObjetivoMin])
            ->selectRaw('SUM(CASE WHEN edad > ? THEN 1 ELSE 0 END) as mayores', [$edadObjetivoMax])
            ->groupBy('municipio_id');

        $capturadasPorMunicipio = \App\Models\Municipio::query()
            ->leftJoinSub($tarjetasAsignadasPorMunicipio, 'tarjetas_asignadas', function ($join) {
                $join->on('tarjetas_asignadas.municipio_id', '=', 'municipios.id');
            })
            ->leftJoinSub($beneficiariosPorMunicipio, 'beneficiarios_resumen', function ($join) {
                $join->on('beneficiarios_resumen.municipio_id', '=', 'municipios.id');
            })
            ->where('municipios.oficina_id', $officeId)
            ->where(function ($query) {
                $query->whereNotNull('tarjetas_asignadas.asignadas')
                    ->orWhereNotNull('beneficiarios_resumen.capturadas');
            })
            ->select([
                'municipios.id',
                'municipios.nombre',
                DB::raw('COALESCE(tarjetas_asignadas.asignadas, 0) as asignadas'),
                DB::raw('COALESCE(beneficiarios_resumen.capturadas, 0) as capturadas'),
                DB::raw('COALESCE(beneficiarios_resumen.edad_objetivo, 0) as edad_objetivo'),
                DB::raw('COALESCE(beneficiarios_resumen.menores, 0) as menores'),
                DB::raw('COALESCE(beneficiarios_resumen.mayores, 0) as mayores'),
            ])
            ->orderByDesc('capturadas')
            ->limit(10)
            ->get();

        return view('delegacion.dashboard', compact(
            'capturadasPorMunicipio',
            'capturadasSummary',
            'edadObjetivoMax',
            'edadObjetivoMin',
            'eventosPorTipo',
            'eventosSummary',
            'inscripcionesPorPrograma',
            'inscripcionesSummary',
            'periodoActual',
            'tarjetasSummary'
        ));
    }

    protected function officeEventosQuery(?int $officeId): Builder
    {
        return Evento::query()
            ->when($officeId, fn (Builder $query) => $query->where('oficina_id', $officeId), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    protected function officeInscripcionesQuery(?int $officeId): Builder
    {
        return Inscripcion::query()
            ->when($officeId, function (Builder $query) use ($officeId) {
                $query->whereHas('beneficiario.municipio', fn (Builder $municipio) => $municipio->where('oficina_id', $officeId));
            }, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    protected function officeTarjetasQuery(?int $officeId): Builder
    {
        return Tarjeta::query()
            ->when($officeId, fn (Builder $query) => $query->where('oficina_id', $officeId), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    protected function officeBeneficiariosQuery(?int $officeId): Builder
    {
        return Beneficiario::query()
            ->when($officeId, function (Builder $query) use ($officeId) {
                $query->whereHas('municipio', fn (Builder $municipio) => $municipio->where('oficina_id', $officeId));
            }, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }
}
