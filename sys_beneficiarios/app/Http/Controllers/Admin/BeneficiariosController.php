<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class BeneficiariosController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Beneficiario::class);

        $filters = $request->only(['municipio_id','seccional','capturista','from','to']);
        $q = Beneficiario::with(['municipio','creador','seccion'])
            ->when($filters['municipio_id'] ?? null, fn($b,$v)=>$b->where('municipio_id',$v))
            ->when($filters['seccional'] ?? null, fn($b,$v)=>$b->whereHas('seccion', fn($sq)=>$sq->where('seccional','like',"%$v%")))
            ->when($filters['capturista'] ?? null, fn($b,$v)=>$b->where('created_by',$v))
            
            ->when($filters['from'] ?? null, fn($b,$v)=>$b->whereDate('created_at','>=',$v))
            ->when($filters['to'] ?? null, fn($b,$v)=>$b->whereDate('created_at','<=',$v))
            ->orderByDesc('created_at');

        $beneficiarios = $q->paginate(50)->withQueryString();
        $municipios = Municipio::orderBy('nombre')->pluck('nombre','id');
        $capturistas = User::role('capturista')->orderBy('name')->get(['uuid','name']);

        return view('admin.beneficiarios.index', compact('beneficiarios','filters','municipios','capturistas'));
    }

    public function show(Beneficiario $beneficiario)
    {
        $this->authorize('view', $beneficiario);
        $activities = Activity::forSubject($beneficiario)->latest()->limit(10)->get();
        return view('admin.beneficiarios.show', compact('beneficiario','activities'));
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Beneficiario::class);
        $filters = $request->only(['municipio_id','seccional','capturista','from','to']);
        $q = Beneficiario::with([
                'municipio',
                'creador',
                'seccion',
                'domicilio.municipio',
                'domicilio.seccion',
            ])
            ->when($filters['municipio_id'] ?? null, fn($b,$v)=>$b->where('municipio_id',$v))
            ->when($filters['seccional'] ?? null, fn($b,$v)=>$b->whereHas('seccion', fn($sq)=>$sq->where('seccional','like',"%$v%")))
            ->when($filters['capturista'] ?? null, fn($b,$v)=>$b->where('created_by',$v))
            
            ->when($filters['from'] ?? null, fn($b,$v)=>$b->whereDate('created_at','>=',$v))
            ->when($filters['to'] ?? null, fn($b,$v)=>$b->whereDate('created_at','<=',$v))
            ->orderByDesc('created_at')
            ->get();

        $headers = [
            'Folio',
            'Nombre',
            'CURP',
            'Edad',
            'Telefono',
            'Municipio',
            'Seccional',
            'Capturista',
            'Fecha',
            'Calle',
            'Numero exterior',
            'Numero interior',
            'Colonia',
            'Municipio domicilio',
            'Municipio domicilio ID',
            'Codigo postal',
            'Seccional domicilio',
            'Distrito local domicilio',
            'Distrito federal domicilio',
        ];
        $callback = function () use ($q, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($q as $b) {
                $dom = $b->domicilio;
                fputcsv($out, [
                    $b->folio_tarjeta,
                    trim($b->nombre.' '.$b->apellido_paterno.' '.$b->apellido_materno),
                    $b->curp,
                    $b->edad,
                    $b->telefono,
                    optional($b->municipio)->nombre,
                    optional($b->seccion)->seccional,
                    optional($b->creador)->name,
                    optional($b->created_at)->format('Y-m-d H:i'),
                    $dom->calle ?? '',
                    $dom->numero_ext ?? '',
                    $dom->numero_int ?? '',
                    $dom->colonia ?? '',
                    optional($dom->municipio)->nombre ?? '',
                    $dom->municipio_id ?? '',
                    $dom->codigo_postal ?? '',
                    optional($dom->seccion)->seccional ?? '',
                    optional($dom->seccion)->distrito_local ?? '',
                    optional($dom->seccion)->distrito_federal ?? '',
                ]);
            }
            fclose($out);
        };

        $filename = 'beneficiarios_admin_'.now()->format('Ymd_His').'.csv';
        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }
}
