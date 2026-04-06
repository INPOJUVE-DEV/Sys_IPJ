<?php

use App\Services\CatalogoCsvSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Utilidad para detectar artefactos de codificacion sospechosos en el codigo fuente
Artisan::command('scan:encoding {--path= : Ruta base a escanear (por defecto, base_path())} {--all : Incluir vendor/node_modules/storage/etc}', function () {
    $base = $this->option('path') ?: base_path();
    $patterns = [
        "\u{FFFD}",
        "\u{00C3}",
        "d\u{00C3}\u{00AD}as",
        "\u{00C3}\u{009A}ltimos",
        "Tel\u{00C3}\u{00A9}fono",
        "N\u{00C3}\u{00BA}mero",
        "Cat\u{00C3}\u{00A1}logos",
    ];
    $exts = ['php', 'blade.php', 'js', 'ts', 'css', 'scss', 'json', 'md', 'yml', 'yaml'];
    $excludeDirs = $this->option('all') ? [] : [
        DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR,
    ];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
    $hits = 0;
    $files = 0;
    $skipped = 0;

    foreach ($rii as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $name = $file->getFilename();
        $path = $file->getPathname();
        $skip = false;

        foreach ($excludeDirs as $exclude) {
            if (str_contains($path, $exclude)) {
                $skip = true;
                break;
            }
        }

        if (! $skip && str_contains($path, DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'console.php')) {
            $skip = true;
        }

        if ($skip) {
            $skipped++;
            continue;
        }

        $lower = strtolower($name);
        $ok = false;
        foreach ($exts as $ext) {
            if (str_ends_with($lower, $ext)) {
                $ok = true;
                break;
            }
        }

        if (! $ok) {
            continue;
        }

        $files++;
        $content = @file_get_contents($path);
        if ($content === false) {
            continue;
        }

        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                $this->line("[hit] $path contains '$pattern'");
                $hits++;
                break;
            }
        }
    }

    $this->info("Scanned $files files under $base. Hits: $hits".($excludeDirs ? ", skipped: $skipped" : ''));
})->purpose('Scan source files for suspicious encoding artifacts');

// Sincronizacion de catalogos (CSV / SQL)
Artisan::command('catalogos:import {--path=} {--municipios=} {--secciones=} {--sql=} {--fresh} {--prune} {--dry-run}', function (CatalogoCsvSyncService $service) {
    $path = $this->option('path') ?: config('catalogos.path', database_path('seeders/data'));
    $municipios = $this->option('municipios') ?: config('catalogos.municipios_file', 'municipios.csv');
    $secciones = $this->option('secciones') ?: config('catalogos.secciones_file', 'secciones.csv');
    $sql = $this->option('sql');
    $fresh = (bool) $this->option('fresh');
    $prune = (bool) $this->option('prune');
    $dryRun = (bool) $this->option('dry-run');

    $this->info('Importando catalogos');
    $this->line('Ruta CSV: '.$path);
    $this->line('Archivo municipios: '.$municipios);
    $this->line('Archivo secciones: '.$secciones);

    if ($fresh) {
        $this->warn('Fresh: limpiando tablas municipios y secciones...');
        \Illuminate\Support\Facades\DB::table('secciones')->truncate();
        \Illuminate\Support\Facades\DB::table('municipios')->truncate();
    }

    if ($sql) {
        $this->info('Ejecutando SQL: '.$sql);
        $sqlContents = @file_get_contents($sql);
        if ($sqlContents === false) {
            $this->error('No se pudo leer el archivo SQL');

            return 1;
        }
        \Illuminate\Support\Facades\DB::unprepared($sqlContents);
    }

    $stats = $service->sync([
        'base_path' => $path,
        'municipios_file' => $municipios,
        'secciones_file' => $secciones,
        'prune' => $prune,
        'dry_run' => $dryRun,
    ]);

    $this->info(($dryRun ? 'Validacion' : 'Sincronizacion').' finalizada');
    $this->line('Municipios: +'.$stats['municipios']['inserted'].' ~'.$stats['municipios']['updated'].' -'.$stats['municipios']['deleted'].' total='.$stats['municipios']['source_total']);
    $this->line('Secciones: +'.$stats['secciones']['inserted'].' ~'.$stats['secciones']['updated'].' -'.$stats['secciones']['deleted'].' omitidas='.$stats['secciones']['skipped'].' total='.$stats['secciones']['source_total']);

    return 0;
})->purpose('Sincroniza catalogos de municipios y secciones desde CSV/SQL con opcion de prune y dry-run');

// Verificacion rapida de Beneficiarios (edad, soft delete, activity log)
Artisan::command('verify:quick', function () {
    $this->info('Verificacion rapida de Beneficiarios');

    $admin = \App\Models\User::where('email', 'admin@example.com')->first();
    if (! $admin) {
        $this->error('No existe el usuario admin@example.com');

        return 1;
    }

    $mun = \App\Models\Municipio::firstOrCreate(['clave' => 9999], ['nombre' => 'Prueba']);
    $seccion = \App\Models\Seccion::firstOrCreate(
        ['seccional' => '0001'],
        ['municipio_id' => $mun->id, 'distrito_local' => 'DL-01', 'distrito_federal' => 'DF-01']
    );

    $beneficiario = new \App\Models\Beneficiario();
    $beneficiario->id = (string) \Illuminate\Support\Str::uuid();
    $beneficiario->folio_tarjeta = 'TEST-'.substr((string) \Illuminate\Support\Str::uuid(), 0, 8);
    $beneficiario->nombre = 'Juan';
    $beneficiario->apellido_paterno = 'Perez';
    $beneficiario->apellido_materno = 'Lopez';
    $rand17 = strtoupper(substr(str_replace('-', '', (string) \Illuminate\Support\Str::uuid()), 0, 1));
    if (! preg_match('/[A-Z\d]/', $rand17)) {
        $rand17 = 'A';
    }
    $rand18 = (string) random_int(0, 9);
    $beneficiario->curp = 'PEPJ000101HDFLRN'.$rand17.$rand18;
    $beneficiario->fecha_nacimiento = '2000-01-01';
    $beneficiario->sexo = 'M';
    $beneficiario->discapacidad = false;
    $beneficiario->id_ine = 'INE123';
    $beneficiario->telefono = '5512345678';
    $beneficiario->municipio_id = $mun->id;
    $beneficiario->seccion()->associate($seccion);
    $beneficiario->created_by = $admin->uuid;
    $beneficiario->save();

    $this->line('Edad calculada (esperado ~'.\Carbon\Carbon::parse('2000-01-01')->age.'): '.$beneficiario->edad);

    $beneficiario->fecha_nacimiento = '1990-01-01';
    $beneficiario->save();
    $this->line('Edad recalculada (esperado ~'.\Carbon\Carbon::parse('1990-01-01')->age.'): '.$beneficiario->edad);

    $domicilio = new \App\Models\Domicilio();
    $domicilio->id = (string) \Illuminate\Support\Str::uuid();
    $domicilio->beneficiario_id = $beneficiario->id;
    $domicilio->calle = 'Falsa';
    $domicilio->numero_ext = '123';
    $domicilio->colonia = 'Centro';
    $domicilio->municipio_id = $mun->id;
    $domicilio->codigo_postal = '01234';
    $domicilio->seccion_id = $seccion->id;
    $domicilio->save();

    $logs = \Illuminate\Support\Facades\DB::table('activity_log')
        ->where('subject_type', \App\Models\Beneficiario::class)
        ->where('subject_id', $beneficiario->id)
        ->count();
    $this->line('Logs de actividad para beneficiario: '.$logs);

    $beneficiario->delete();
    $trashed = \App\Models\Beneficiario::withTrashed()->where('id', $beneficiario->id)->whereNotNull('deleted_at')->exists();
    $this->line('Soft delete aplicado: '.($trashed ? 'si' : 'no'));

    $this->info('OK');

    return 0;
})->purpose('Ejecuta verificacion rapida de modelo Beneficiario');
