<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiTjInboundRequest;
use App\Models\ApiTjSyncRun;
use App\Models\Beneficiario;
use App\Services\ApiTjInboundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ApiTjDashboardController extends Controller
{
    public function __construct(private readonly ApiTjInboundService $inboundService)
    {
    }

    public function index()
    {
        $readiness = $this->readiness();

        $summary = [
            'pending_sync' => $readiness['beneficiarios_ready']
                ? Beneficiario::where('api_tj_sync_status', Beneficiario::API_TJ_SYNC_STATUS_PENDING_SYNC)->count()
                : 0,
            'pending_data' => $readiness['beneficiarios_ready']
                ? Beneficiario::where('api_tj_sync_status', Beneficiario::API_TJ_SYNC_STATUS_PENDING_DATA)->count()
                : 0,
            'synced' => $readiness['beneficiarios_ready']
                ? Beneficiario::where('api_tj_sync_status', Beneficiario::API_TJ_SYNC_STATUS_SYNCED)->count()
                : 0,
            'sync_failed' => $readiness['beneficiarios_ready']
                ? Beneficiario::where('api_tj_sync_status', Beneficiario::API_TJ_SYNC_STATUS_SYNC_FAILED)->count()
                : 0,
            'inbound_processed' => $readiness['inbound_ready']
                ? ApiTjInboundRequest::where('status', ApiTjInboundRequest::STATUS_PROCESSED)->count()
                : 0,
            'inbound_failed' => $readiness['inbound_ready']
                ? ApiTjInboundRequest::where('status', ApiTjInboundRequest::STATUS_FAILED)->count()
                : 0,
        ];

        $recentRequests = $readiness['inbound_ready']
            ? ApiTjInboundRequest::query()
                ->latest('received_at')
                ->limit(5)
                ->get()
            : collect();

        $recentSyncRuns = $readiness['sync_runs_ready']
            ? ApiTjSyncRun::query()
                ->with('actor')
                ->latest('started_at')
                ->limit(5)
                ->get()
            : collect();

        $samplePayload = [
            'external_request_id' => 'QA-'.now()->format('Ymd-His'),
            'records' => [[
                'curp' => 'PEPJ800101HDFRRN09',
                'nombre' => 'JUAN',
                'apellido_paterno' => 'PEREZ',
                'apellido_materno' => 'LOPEZ',
                'fecha_nacimiento' => '1980-01-01',
                'telefono' => '5512345678',
                'email' => null,
                'folio_tarjeta' => 'TJ-000123',
                'domicilio' => [
                    'calle' => 'CALLE 1',
                    'numero_ext' => '10',
                    'numero_int' => '2',
                    'colonia' => 'CENTRO',
                    'municipio_id' => 1,
                    'codigo_postal' => '01000',
                    'seccional' => '0001',
                ],
            ]],
        ];

        return view('admin.api_tj.index', compact('summary', 'recentRequests', 'recentSyncRuns', 'samplePayload', 'readiness'));
    }

    public function simulateInbound(Request $request)
    {
        if (! $this->readiness()['qa_inbound_ready']) {
            return back()->withErrors([
                'payload_json' => 'Faltan migraciones de API_TJ. Ejecuta `php artisan migrate` antes de usar la consola QA.',
            ]);
        }

        $validated = $request->validate([
            'payload_json' => ['required', 'string'],
        ]);

        $payload = json_decode($validated['payload_json'], true);
        if (! is_array($payload)) {
            return back()
                ->withInput()
                ->withErrors(['payload_json' => 'El JSON no tiene un formato valido.']);
        }

        // Esta consola es solo para QA interno y reutiliza exactamente el mismo servicio del endpoint.
        $result = $this->inboundService->processBatch($payload);
        $requestRecord = ApiTjInboundRequest::query()
            ->when(! empty($payload['external_request_id']), function ($query) use ($payload) {
                $query->where('external_request_id', $payload['external_request_id']);
            }, function ($query) use ($payload) {
                $query->where('request_hash', hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            })
            ->latest('created_at')
            ->first();

        if (! $requestRecord) {
            return back()->with('status', 'La prueba inbound se ejecuto, pero no se localizo la auditoria para abrirla.');
        }

        return redirect()
            ->route('admin.api-tj.requests.show', $requestRecord)
            ->with('status', 'Prueba inbound ejecutada. Aceptados: '.($result['body']['accepted_count'] ?? 0).', rechazados: '.($result['body']['rejected_count'] ?? 0).'.');
    }

    public function syncRunsIndex()
    {
        abort_unless($this->readiness()['sync_runs_ready'], 503, 'Faltan migraciones de API_TJ para consultar el historial de sincronizacion.');

        $syncRuns = ApiTjSyncRun::query()
            ->with('actor')
            ->latest('started_at')
            ->paginate(20);

        return view('admin.api_tj.sync_runs.index', compact('syncRuns'));
    }

    public function syncRunsShow(ApiTjSyncRun $syncRun)
    {
        abort_unless($this->readiness()['sync_runs_ready'], 503, 'Faltan migraciones de API_TJ para consultar el historial de sincronizacion.');

        $syncRun->load('actor');

        return view('admin.api_tj.sync_runs.show', compact('syncRun'));
    }

    private function readiness(): array
    {
        $beneficiariosReady = Schema::hasTable('beneficiarios')
            && Schema::hasColumn('beneficiarios', 'status')
            && Schema::hasColumn('beneficiarios', 'api_tj_sync_status');

        $inboundReady = Schema::hasTable('api_tj_inbound_requests');
        $syncRunsReady = Schema::hasTable('api_tj_sync_runs');

        $missing = [];

        if (! $beneficiariosReady) {
            $missing[] = 'campos API_TJ en beneficiarios';
        }

        if (! $inboundReady) {
            $missing[] = 'tabla api_tj_inbound_requests';
        }

        if (! $syncRunsReady) {
            $missing[] = 'tabla api_tj_sync_runs';
        }

        return [
            'beneficiarios_ready' => $beneficiariosReady,
            'inbound_ready' => $inboundReady,
            'sync_runs_ready' => $syncRunsReady,
            'qa_inbound_ready' => $beneficiariosReady && $inboundReady,
            'sync_ready' => $beneficiariosReady && $syncRunsReady,
            'warning' => empty($missing)
                ? null
                : 'Configuracion pendiente de API_TJ: faltan '.implode(', ', $missing).'. Ejecuta `php artisan migrate` y recarga esta pantalla.',
        ];
    }
}
