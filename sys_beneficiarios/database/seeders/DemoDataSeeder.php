<?php

namespace Database\Seeders;

use App\Models\Beneficiario;
use App\Models\Domicilio;
use App\Models\Inscripcion;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Programa;
use App\Models\Seccion;
use App\Models\Tarjeta;
use App\Models\User;
use App\Models\ValeBloc;
use App\Services\TarjetaService;
use App\Services\ValeBlocService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'Password123';
    private const CARD_PREFIX = 'TAR-';
    private const CARD_PADDING = 6;

    public function run(): void
    {
        $this->seedFallbackCatalogos();
        $this->assignMunicipiosToDelegaciones();

        $users = $this->seedUsers();
        $programas = $this->seedProgramas();

        $this->seedTarjetas($users['admin'], $users['capturistas_by_office']);
        $this->seedVales($users['admin'], $users['capturistas_by_office']);
        $this->seedBeneficiarios($users['capturistas_by_office'], $programas);
    }

    private function seedFallbackCatalogos(): void
    {
        if (Municipio::count() === 0) {
            for ($index = 1; $index <= 8; $index++) {
                Municipio::updateOrCreate(
                    ['clave' => 9000 + $index],
                    ['nombre' => 'Municipio Demo '.$index]
                );
            }
        }

        if (Seccion::count() === 0) {
            $counter = 8801;
            $municipios = Municipio::orderBy('clave')->get();

            foreach ($municipios as $municipio) {
                for ($slot = 1; $slot <= 2; $slot++) {
                    Seccion::updateOrCreate(
                        ['seccional' => str_pad((string) $counter, 4, '0', STR_PAD_LEFT)],
                        [
                            'municipio_id' => $municipio->id,
                            'distrito_local' => 'DL-'.str_pad((string) $slot, 2, '0', STR_PAD_LEFT),
                            'distrito_federal' => 'DF-'.str_pad((string) $slot, 2, '0', STR_PAD_LEFT),
                        ]
                    );
                    $counter++;
                }
            }
        }
    }

    private function assignMunicipiosToDelegaciones(): void
    {
        $delegaciones = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->get()->values();
        if ($delegaciones->isEmpty()) {
            return;
        }

        $unassigned = Municipio::whereNull('oficina_id')->orderBy('clave')->get()->values();
        foreach ($unassigned as $index => $municipio) {
            $target = $delegaciones[$index % $delegaciones->count()];
            $municipio->forceFill(['oficina_id' => $target->id])->save();
        }
    }

    private function seedUsers(): array
    {
        $admin = $this->upsertUser('admin@example.com', 'Administrador', 'admin');
        $this->upsertUser('capturista_programas@example.com', 'Capturista Programas', 'capturista_programas');

        $capturistasByOffice = [];
        $delegaciones = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->get();

        foreach ($delegaciones as $officeIndex => $office) {
            $slot = $officeIndex + 1;

            $this->upsertUser(
                "delegado{$slot}@example.com",
                'Delegado '.$office->nombre,
                'delegado',
                $office->id
            );

            $capturistasByOffice[$office->id] = [
                $this->upsertUser(
                    "capturista{$slot}a@example.com",
                    'Capturista '.$office->nombre.' A',
                    'capturista',
                    $office->id
                ),
                $this->upsertUser(
                    "capturista{$slot}b@example.com",
                    'Capturista '.$office->nombre.' B',
                    'capturista',
                    $office->id
                ),
            ];
        }

        return [
            'admin' => $admin,
            'capturistas_by_office' => $capturistasByOffice,
        ];
    }

    private function seedProgramas(): array
    {
        $rows = [
            [
                'nombre' => 'Tarjeta Joven',
                'slug' => 'tarjeta-joven',
                'tipo_periodo' => 'mensual',
                'renovable' => true,
                'activo' => true,
            ],
            [
                'nombre' => 'Apoyo de Transporte',
                'slug' => 'apoyo-de-transporte',
                'tipo_periodo' => 'mensual',
                'renovable' => true,
                'activo' => true,
            ],
            [
                'nombre' => 'Talleres Comunitarios',
                'slug' => 'talleres-comunitarios',
                'tipo_periodo' => 'mensual',
                'renovable' => false,
                'activo' => true,
            ],
            [
                'nombre' => 'Impulso Deportivo',
                'slug' => 'impulso-deportivo',
                'tipo_periodo' => 'mensual',
                'renovable' => false,
                'activo' => true,
            ],
        ];

        $programas = [];
        foreach ($rows as $row) {
            $programa = Programa::updateOrCreate(
                ['slug' => $row['slug']],
                Arr::except($row, ['slug']) + ['slug' => $row['slug']]
            );
            $programas[$programa->slug] = $programa;
        }

        return $programas;
    }

    private function seedTarjetas(User $admin, array $capturistasByOffice): void
    {
        if (Tarjeta::count() > 0) {
            return;
        }

        $service = app(TarjetaService::class);
        $central = Oficina::where('tipo', Oficina::TIPO_CENTRAL)->firstOrFail();
        $delegaciones = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->get()->values();

        $service->createRange($admin, $central, self::CARD_PREFIX, 1, 480, self::CARD_PADDING, 'Carga demo local');

        foreach ($delegaciones as $officeIndex => $office) {
            $start = ($officeIndex * 100) + 1;
            $end = $start + 99;

            $service->transferRange(
                $admin,
                $office,
                self::CARD_PREFIX,
                $start,
                $end,
                self::CARD_PADDING,
                'Distribucion inicial demo'
            );

            $capturistas = $capturistasByOffice[$office->id] ?? [];
            if (isset($capturistas[0])) {
                $service->assignRangeToUser(
                    $admin,
                    $capturistas[0],
                    self::CARD_PREFIX,
                    $start,
                    $start + 14,
                    self::CARD_PADDING,
                    'Asignacion demo A'
                );
            }

            if (isset($capturistas[1])) {
                $service->assignRangeToUser(
                    $admin,
                    $capturistas[1],
                    self::CARD_PREFIX,
                    $start + 15,
                    $start + 29,
                    self::CARD_PADDING,
                    'Asignacion demo B'
                );
            }
        }
    }

    private function seedVales(User $admin, array $capturistasByOffice): void
    {
        if (ValeBloc::count() > 0) {
            return;
        }

        $service = app(ValeBlocService::class);
        $central = Oficina::where('tipo', Oficina::TIPO_CENTRAL)->firstOrFail();
        $delegaciones = Oficina::where('tipo', Oficina::TIPO_DELEGACION)->orderBy('id')->get()->values();
        $rangeStart = 1000;

        foreach ($delegaciones as $officeIndex => $office) {
            $officeBloc = $service->createBlock($admin, $central, $rangeStart, $rangeStart + 999, 'Bloc demo oficina');
            $service->transfer($admin, $officeBloc, $office, 'Resguardo demo delegacion');
            $rangeStart += 1000;

            $userBloc = $service->createBlock($admin, $central, $rangeStart, $rangeStart + 999, 'Bloc demo usuario');
            $service->transfer($admin, $userBloc, $office, 'Entrega demo delegacion');

            $capturista = $capturistasByOffice[$office->id][0] ?? null;
            if ($capturista) {
                $service->assignToUser($admin, $userBloc, $capturista, 'Asignacion demo a capturista');
            }

            if ($officeIndex === 1) {
                $service->markStatus($admin, $officeBloc, ValeBloc::STATUS_CERRADO, 'Bloc demo cerrado');
            }

            if ($officeIndex === 2 && $capturista) {
                $service->markStatus($admin, $userBloc, ValeBloc::STATUS_BLOQUEADO, 'Bloc demo bloqueado');
            }

            $rangeStart += 1000;
        }

        $service->createBlock($admin, $central, $rangeStart, $rangeStart + 999, 'Bloc demo disponible en central');
    }

    private function seedBeneficiarios(array $capturistasByOffice, array $programas): void
    {
        if (Beneficiario::count() > 0) {
            return;
        }

        $service = app(TarjetaService::class);
        $profiles = $this->demoProfiles();
        $profileIndex = 0;

        foreach ($capturistasByOffice as $officeId => $capturistas) {
            $office = Oficina::findOrFail($officeId);

            foreach ($capturistas as $slot => $capturista) {
                $profile = $profiles[$profileIndex] ?? null;
                if (! $profile) {
                    break 2;
                }

                $seccion = $this->sectionForOffice($office, $slot);
                $beneficiario = Beneficiario::firstOrNew(['curp' => $profile['curp']]);

                if (! $beneficiario->exists) {
                    $beneficiario->forceFill([
                        'id' => (string) Str::uuid(),
                        'nombre' => $profile['nombre'],
                        'apellido_paterno' => $profile['apellido_paterno'],
                        'apellido_materno' => $profile['apellido_materno'],
                        'curp' => $profile['curp'],
                        'fecha_nacimiento' => $profile['fecha_nacimiento'],
                        'sexo' => $profile['sexo'],
                        'discapacidad' => $profile['discapacidad'],
                        'id_ine' => $profile['id_ine'],
                        'telefono' => $profile['telefono'],
                        'municipio_id' => $seccion->municipio_id,
                        'seccion_id' => $seccion->id,
                        'created_by' => $capturista->uuid,
                    ])->save();
                }

                Domicilio::updateOrCreate(
                    ['beneficiario_id' => $beneficiario->id],
                    [
                        'id' => Domicilio::where('beneficiario_id', $beneficiario->id)->value('id') ?: (string) Str::uuid(),
                        'calle' => 'Calle Demo '.($profileIndex + 1),
                        'numero_ext' => (string) (100 + $profileIndex),
                        'numero_int' => null,
                        'colonia' => 'Centro',
                        'municipio_id' => $seccion->municipio_id,
                        'codigo_postal' => '420'.str_pad((string) $profileIndex, 2, '0', STR_PAD_LEFT),
                        'seccion_id' => $seccion->id,
                    ]
                );

                if (! $beneficiario->tarjeta_id) {
                    $tarjeta = Tarjeta::where('usuario_uuid', $capturista->uuid)
                        ->where('estatus', Tarjeta::STATUS_ASIGNADA_USUARIO)
                        ->orderBy('folio')
                        ->first();

                    if ($tarjeta) {
                        $service->consume($capturista, $tarjeta, $beneficiario);
                        $beneficiario->forceFill([
                            'tarjeta_id' => $tarjeta->id,
                            'folio_tarjeta' => $tarjeta->folio,
                        ])->save();
                    }
                }

                foreach ($profile['programas'] as $slug) {
                    $programa = $programas[$slug] ?? null;
                    if (! $programa) {
                        continue;
                    }

                    Inscripcion::firstOrCreate(
                        [
                            'beneficiario_id' => $beneficiario->id,
                            'programa_id' => $programa->id,
                            'periodo' => now()->format('Y-m'),
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'estatus' => 'inscrito',
                            'created_by' => $capturista->uuid,
                        ]
                    );
                }

                $profileIndex++;
            }
        }

        $this->seedCardIncidentStates();
    }

    private function seedCardIncidentStates(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (! $admin) {
            return;
        }

        $service = app(TarjetaService::class);
        $targets = [
            ['folio' => $this->formatCardFolio(15), 'status' => Tarjeta::STATUS_DEVUELTA, 'note' => 'Tarjeta demo devuelta'],
            ['folio' => $this->formatCardFolio(130), 'status' => Tarjeta::STATUS_BLOQUEADA, 'note' => 'Tarjeta demo bloqueada'],
            ['folio' => $this->formatCardFolio(230), 'status' => Tarjeta::STATUS_EXTRAVIADA, 'note' => 'Tarjeta demo extraviada'],
        ];

        foreach ($targets as $target) {
            $tarjeta = Tarjeta::where('folio', $target['folio'])->first();
            if (! $tarjeta || $tarjeta->estatus === Tarjeta::STATUS_CONSUMIDA || $tarjeta->estatus === $target['status']) {
                continue;
            }

            $service->markStatus($admin, $tarjeta, $target['status'], $target['note']);
        }
    }

    private function sectionForOffice(Oficina $office, int $offset = 0): Seccion
    {
        $section = Seccion::whereHas('municipio', fn ($query) => $query->where('oficina_id', $office->id))
            ->orderBy('seccional')
            ->skip($offset)
            ->first();

        if ($section) {
            return $section;
        }

        $municipio = Municipio::where('oficina_id', $office->id)->orderBy('clave')->first();
        if (! $municipio) {
            $municipio = Municipio::create([
                'clave' => 9800 + $office->id,
                'nombre' => 'Municipio '.$office->nombre,
                'oficina_id' => $office->id,
            ]);
        }

        return Seccion::firstOrCreate(
            ['seccional' => str_pad((string) (8900 + $office->id + $offset), 4, '0', STR_PAD_LEFT)],
            [
                'municipio_id' => $municipio->id,
                'distrito_local' => 'DL-01',
                'distrito_federal' => 'DF-01',
            ]
        );
    }

    private function upsertUser(string $email, string $name, string $role, ?int $officeId = null): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'oficina_id' => $officeId,
            ]
        );

        $user->syncRoles([$role]);

        return $user;
    }

    private function formatCardFolio(int $number): string
    {
        return self::CARD_PREFIX.str_pad((string) $number, self::CARD_PADDING, '0', STR_PAD_LEFT);
    }

    private function demoProfiles(): array
    {
        return [
            [
                'nombre' => 'Ana',
                'apellido_paterno' => 'Perez',
                'apellido_materno' => 'Lopez',
                'curp' => 'PELA000101MDFRPNA1',
                'fecha_nacimiento' => '2000-01-01',
                'sexo' => 'F',
                'discapacidad' => false,
                'id_ine' => 'INE-DEMO-001',
                'telefono' => '5510000001',
                'programas' => ['tarjeta-joven', 'apoyo-de-transporte'],
            ],
            [
                'nombre' => 'Jose',
                'apellido_paterno' => 'Ramirez',
                'apellido_materno' => 'Soto',
                'curp' => 'RASJ990203HDFMTRA2',
                'fecha_nacimiento' => '1999-02-03',
                'sexo' => 'M',
                'discapacidad' => false,
                'id_ine' => 'INE-DEMO-002',
                'telefono' => '5510000002',
                'programas' => ['talleres-comunitarios'],
            ],
            [
                'nombre' => 'Maria',
                'apellido_paterno' => 'Torres',
                'apellido_materno' => 'Garcia',
                'curp' => 'TOGM010405MDFRCRA3',
                'fecha_nacimiento' => '2001-04-05',
                'sexo' => 'F',
                'discapacidad' => true,
                'id_ine' => 'INE-DEMO-003',
                'telefono' => '5510000003',
                'programas' => ['impulso-deportivo', 'tarjeta-joven'],
            ],
            [
                'nombre' => 'Luis',
                'apellido_paterno' => 'Vargas',
                'apellido_materno' => 'Neri',
                'curp' => 'VANL980711HDFRRSA4',
                'fecha_nacimiento' => '1998-07-11',
                'sexo' => 'M',
                'discapacidad' => false,
                'id_ine' => 'INE-DEMO-004',
                'telefono' => '5510000004',
                'programas' => ['apoyo-de-transporte'],
            ],
            [
                'nombre' => 'Carla',
                'apellido_paterno' => 'Morales',
                'apellido_materno' => 'Santos',
                'curp' => 'MOSC020112MDFRLRA5',
                'fecha_nacimiento' => '2002-01-12',
                'sexo' => 'F',
                'discapacidad' => false,
                'id_ine' => 'INE-DEMO-005',
                'telefono' => '5510000005',
                'programas' => ['talleres-comunitarios', 'tarjeta-joven'],
            ],
            [
                'nombre' => 'Diego',
                'apellido_paterno' => 'Salas',
                'apellido_materno' => 'Pineda',
                'curp' => 'SAPD970822HDFLNXA6',
                'fecha_nacimiento' => '1997-08-22',
                'sexo' => 'M',
                'discapacidad' => false,
                'id_ine' => 'INE-DEMO-006',
                'telefono' => '5510000006',
                'programas' => ['impulso-deportivo'],
            ],
            [
                'nombre' => 'Monica',
                'apellido_paterno' => 'Cabrera',
                'apellido_materno' => 'Bautista',
                'curp' => 'CABM000914MDFRNSA7',
                'fecha_nacimiento' => '2000-09-14',
                'sexo' => 'F',
                'discapacidad' => false,
                'id_ine' => 'INE-DEMO-007',
                'telefono' => '5510000007',
                'programas' => ['tarjeta-joven'],
            ],
            [
                'nombre' => 'Tomas',
                'apellido_paterno' => 'Navarro',
                'apellido_materno' => 'Guerrero',
                'curp' => 'NAGT991125HDFVRMA8',
                'fecha_nacimiento' => '1999-11-25',
                'sexo' => 'M',
                'discapacidad' => false,
                'id_ine' => 'INE-DEMO-008',
                'telefono' => '5510000008',
                'programas' => ['apoyo-de-transporte', 'impulso-deportivo'],
            ],
        ];
    }
}
