<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            if (!Schema::hasColumn('beneficiarios', 'seccion_id')) {
                $table->foreignId('seccion_id')
                    ->nullable()
                    ->after('municipio_id')
                    ->constrained('secciones')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });

        Schema::table('domicilios', function (Blueprint $table) {
            if (!Schema::hasColumn('domicilios', 'seccion_id')) {
                $table->foreignId('seccion_id')
                    ->nullable()
                    ->after('municipio_id')
                    ->constrained('secciones')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });

        $this->backfillBeneficiarios();
        $this->backfillDomicilios();

        Schema::table('beneficiarios', function (Blueprint $table) {
            if (Schema::hasColumn('beneficiarios', 'seccional')) {
                $table->dropColumn(['seccional', 'distrito_local', 'distrito_federal']);
            }
        });

        Schema::table('domicilios', function (Blueprint $table) {
            if (Schema::hasColumn('domicilios', 'municipio')) {
                $table->dropColumn(['municipio']);
            }
            if (Schema::hasColumn('domicilios', 'seccional')) {
                $table->dropColumn(['seccional', 'distrito_local', 'distrito_federal']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            if (!Schema::hasColumn('beneficiarios', 'seccional')) {
                $table->string('seccional')->nullable()->after('municipio_id');
            }
            if (!Schema::hasColumn('beneficiarios', 'distrito_local')) {
                $table->string('distrito_local')->nullable()->after('seccional');
            }
            if (!Schema::hasColumn('beneficiarios', 'distrito_federal')) {
                $table->string('distrito_federal')->nullable()->after('distrito_local');
            }
        });

        Schema::table('domicilios', function (Blueprint $table) {
            if (!Schema::hasColumn('domicilios', 'municipio')) {
                $table->string('municipio')->nullable()->after('colonia');
            }
            if (!Schema::hasColumn('domicilios', 'seccional')) {
                $table->string('seccional')->nullable()->after('codigo_postal');
            }
            if (!Schema::hasColumn('domicilios', 'distrito_local')) {
                $table->string('distrito_local')->nullable()->after('seccional');
            }
            if (!Schema::hasColumn('domicilios', 'distrito_federal')) {
                $table->string('distrito_federal')->nullable()->after('distrito_local');
            }
        });

        $this->restoreLegacyBeneficiarioFields();
        $this->restoreLegacyDomicilioFields();

        Schema::table('beneficiarios', function (Blueprint $table) {
            if (Schema::hasColumn('beneficiarios', 'seccion_id')) {
                $table->dropConstrainedForeignId('seccion_id');
            }
        });

        Schema::table('domicilios', function (Blueprint $table) {
            if (Schema::hasColumn('domicilios', 'seccion_id')) {
                $table->dropConstrainedForeignId('seccion_id');
            }
        });
    }

    private function backfillBeneficiarios(): void
    {
        if (!Schema::hasColumn('beneficiarios', 'seccional')) {
            return;
        }

        $map = DB::table('secciones')
            ->select(['id', 'seccional', 'municipio_id'])
            ->get()
            ->reduce(function ($carry, $item) {
                $carry[$item->seccional] = $item;
                return $carry;
            }, []);

        DB::table('beneficiarios')
            ->select(['id', 'seccional', 'municipio_id'])
            ->orderBy('id')
            ->lazy()
            ->each(function ($row) use ($map) {
                $seccion = $this->matchSeccion($row->seccional, $map);
                if (! $seccion) {
                    return;
                }
                $municipioId = $row->municipio_id ?: $seccion->municipio_id;
                DB::table('beneficiarios')
                    ->where('id', $row->id)
                    ->update([
                        'seccion_id' => $seccion->id,
                        'municipio_id' => $municipioId,
                    ]);
            });
    }

    private function backfillDomicilios(): void
    {
        if (!Schema::hasColumn('domicilios', 'seccional') && !Schema::hasColumn('domicilios', 'municipio')) {
            return;
        }

        $map = DB::table('secciones')
            ->select(['id', 'seccional', 'municipio_id'])
            ->get()
            ->reduce(function ($carry, $item) {
                $carry[$item->seccional] = $item;
                return $carry;
            }, []);

        DB::table('domicilios')
            ->select(['id', 'seccional', 'municipio_id', 'municipio'])
            ->orderBy('id')
            ->lazy()
            ->each(function ($row) use ($map) {
                $seccion = $this->matchSeccion($row->seccional, $map);
                $municipioId = $row->municipio_id;
                if (! $municipioId && $row->municipio) {
                    $municipioId = DB::table('municipios')->where('nombre', $row->municipio)->value('id');
                }
                if ($seccion) {
                    $municipioId = $municipioId ?: $seccion->municipio_id;
                }
                DB::table('domicilios')
                    ->where('id', $row->id)
                    ->update([
                        'seccion_id' => $seccion->id ?? null,
                        'municipio_id' => $municipioId,
                    ]);
            });
    }

    private function restoreLegacyBeneficiarioFields(): void
    {
        $secciones = DB::table('secciones')->select(['id', 'seccional', 'distrito_local', 'distrito_federal'])->get()
            ->keyBy('id');

        DB::table('beneficiarios')
            ->select(['id', 'seccion_id'])
            ->orderBy('id')
            ->lazy()
            ->each(function ($row) use ($secciones) {
                $seccion = $row->seccion_id ? $secciones->get($row->seccion_id) : null;
                DB::table('beneficiarios')
                    ->where('id', $row->id)
                    ->update([
                        'seccional' => $seccion->seccional ?? null,
                        'distrito_local' => $seccion->distrito_local ?? null,
                        'distrito_federal' => $seccion->distrito_federal ?? null,
                    ]);
            });
    }

    private function restoreLegacyDomicilioFields(): void
    {
        $secciones = DB::table('secciones')->select(['id', 'seccional', 'distrito_local', 'distrito_federal', 'municipio_id'])->get()
            ->keyBy('id');

        $municipios = DB::table('municipios')->select(['id', 'nombre'])->get()->keyBy('id');

        DB::table('domicilios')
            ->select(['id', 'seccion_id', 'municipio_id'])
            ->orderBy('id')
            ->lazy()
            ->each(function ($row) use ($secciones, $municipios) {
                $seccion = $row->seccion_id ? $secciones->get($row->seccion_id) : null;
                $municipio = $row->municipio_id ? $municipios->get($row->municipio_id) : null;
                DB::table('domicilios')
                    ->where('id', $row->id)
                    ->update([
                        'municipio' => $municipio->nombre ?? null,
                        'seccional' => $seccion->seccional ?? null,
                        'distrito_local' => $seccion->distrito_local ?? null,
                        'distrito_federal' => $seccion->distrito_federal ?? null,
                    ]);
            });
    }

    private function matchSeccion(?string $value, array $map): ?object
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        $candidates = array_unique([
            $value,
            ltrim($value, '0'),
            str_pad(ltrim($value, '0'), 4, '0', STR_PAD_LEFT),
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (isset($map[$candidate])) {
                return $map[$candidate];
            }
        }

        return null;
    }
};
