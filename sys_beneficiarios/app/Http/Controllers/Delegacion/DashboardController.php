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
        $tarjetasCapturadasBase = $this->officeCapturedTarjetasQuery($officeId);

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
                ->whereIn('estatus', [Tarjeta::STATUS_ASIGNADA_OFICINA, Tarjeta::STATUS_DEVUELTA])
                ->count(),
            'municipio' => $this->officeTarjetasQuery($officeId)
                ->whereNotNull('municipio_id')
                ->whereNotIn('estatus', [
                    Tarjeta::STATUS_CONSUMIDA,
                    Tarjeta::STATUS_BLOQUEADA,
                    Tarjeta::STATUS_EXTRAVIADA,
                ])
                ->count(),
            'capturadas' => $this->officeTarjetasQuery($officeId)
                ->where('estatus', Tarjeta::STATUS_CONSUMIDA)
                ->count(),
            'incidencias' => $this->officeTarjetasQuery($officeId)
                ->whereIn('estatus', [Tarjeta::STATUS_BLOQUEADA, Tarjeta::STATUS_EXTRAVIADA])
                ->count(),
        ];

        $capturadasConBeneficiario = (clone $tarjetasCapturadasBase)
            ->whereNotNull('beneficiario_id')
            ->count();

        $capturadasEdadObjetivo = (clone $tarjetasCapturadasBase)
            ->whereHas('beneficiario', fn (Builder $query) => $query->whereBetween('edad', [$edadObjetivoMin, $edadObjetivoMax]))
            ->count();

        $capturadasMenores = (clone $tarjetasCapturadasBase)
            ->whereHas('beneficiario', fn (Builder $query) => $query->where('edad', '<', $edadObjetivoMin))
            ->count();

        $capturadasMayores = (clone $tarjetasCapturadasBase)
            ->whereHas('beneficiario', fn (Builder $query) => $query->where('edad', '>', $edadObjetivoMax))
            ->count();

        $capturadasPorSexo = Beneficiario::query()
            ->whereBetween('edad', [$edadObjetivoMin, $edadObjetivoMax])
            ->whereHas('tarjeta', function (Builder $query) use ($officeId) {
                $query->where('estatus', Tarjeta::STATUS_CONSUMIDA)
                    ->where('oficina_id', $officeId)
                    ->whereHas('municipio', fn (Builder $municipio) => $municipio->where('oficina_id', $officeId));
            })
            ->select([
                'sexo',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('sexo')
            ->pluck('total', 'sexo');

        $capturadasSummary = [
            'total' => (clone $tarjetasCapturadasBase)->count(),
            'mes' => (clone $tarjetasCapturadasBase)->whereBetween('updated_at', [$monthStart, $now])->count(),
            'hoy' => (clone $tarjetasCapturadasBase)->whereBetween('updated_at', [$todayStart, $now])->count(),
            'con_beneficiario' => $capturadasConBeneficiario,
            'edad_objetivo' => $capturadasEdadObjetivo,
            'menores' => $capturadasMenores,
            'mayores' => $capturadasMayores,
            'sin_beneficiario' => (clone $tarjetasCapturadasBase)->count() - $capturadasConBeneficiario,
            'mujeres_objetivo' => (int) ($capturadasPorSexo['F'] ?? 0),
            'hombres_objetivo' => (int) ($capturadasPorSexo['M'] ?? 0),
            'otro_objetivo' => (int) ($capturadasPorSexo['X'] ?? 0),
        ];

        $capturadasPorMunicipio = Tarjeta::query()
            ->with('municipio:id,nombre')
            ->leftJoin('beneficiarios', 'tarjetas.beneficiario_id', '=', 'beneficiarios.id')
            ->select([
                'tarjetas.municipio_id',
                DB::raw('COUNT(*) as capturadas'),
                DB::raw('(
                    SELECT COUNT(*)
                    FROM tarjetas as tarjetas_asignadas
                    WHERE tarjetas_asignadas.oficina_id = tarjetas.oficina_id
                        AND tarjetas_asignadas.municipio_id = tarjetas.municipio_id
                ) as asignadas'),
            ])
            ->selectRaw('SUM(CASE WHEN beneficiarios.edad BETWEEN ? AND ? THEN 1 ELSE 0 END) as edad_objetivo', [$edadObjetivoMin, $edadObjetivoMax])
            ->selectRaw('SUM(CASE WHEN beneficiarios.edad < ? THEN 1 ELSE 0 END) as menores', [$edadObjetivoMin])
            ->selectRaw('SUM(CASE WHEN beneficiarios.edad > ? THEN 1 ELSE 0 END) as mayores', [$edadObjetivoMax])
            ->where('tarjetas.estatus', Tarjeta::STATUS_CONSUMIDA)
            ->where('tarjetas.oficina_id', $officeId)
            ->whereNotNull('tarjetas.municipio_id')
            ->whereExists(function ($query) use ($officeId) {
                $query->selectRaw('1')
                    ->from('municipios')
                    ->whereColumn('municipios.id', 'tarjetas.municipio_id')
                    ->where('municipios.oficina_id', $officeId);
            })
            ->groupBy('tarjetas.municipio_id', 'tarjetas.oficina_id')
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

    protected function officeCapturedTarjetasQuery(?int $officeId): Builder
    {
        return Tarjeta::query()
            ->where('estatus', Tarjeta::STATUS_CONSUMIDA)
            ->when($officeId, function (Builder $query) use ($officeId) {
                $query->where('oficina_id', $officeId)
                    ->whereHas('municipio', fn (Builder $municipio) => $municipio->where('oficina_id', $officeId));
            }, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }
}
