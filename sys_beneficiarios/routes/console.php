<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Utilidad para detectar artefactos de codificación sospechosos en el código fuente
Artisan::command('scan:encoding {--path= : Ruta base a escanear (por defecto, base_path())} {--all : Incluir vendor/node_modules/storage/etc}', function () {
    $base = $this->option('path') ?: base_path();
    // Patrones comunes de mojibake en español
    $patterns = [
        '�',        // Unicode replacement char
        'Ã',        // Indicativo de UTF-8 mal interpretado (Ã¡, Ã³, etc.)
        'dA-as',    // "días" roto
        'Asltimos', // "Últimos" roto
        'TelA',     // "Teléfono" roto
        'NA�mero',  // "Número" roto
        'CatA',     // "Catálogos" roto
    ];
    $exts = ['php','blade.php','js','ts','css','scss','json','md','yml','yaml'];
    $excludeDirs = $this->option('all') ? [] : [
        DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR,
    ];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
    $hits = 0; $files = 0; $skipped = 0;
    foreach ($rii as $file) {
        if (!$file->isFile()) continue;
        $name = $file->getFilename();
        $path = $file->getPathname();
        // Excluir rutas no relevantes por defecto
        $skip = false;
        foreach ($excludeDirs as $ex) {
            if (str_contains($path, $ex)) { $skip = true; break; }
        }
        // Evitar auto-reportarse por contener patrones en este archivo
        if (!$skip && str_contains($path, DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'console.php')) {
            $skip = true;
        }
        if ($skip) { $skipped++; continue; }
        $lower = strtolower($name);
        $ok = false;
        foreach ($exts as $e) {
            if (str_ends_with($lower, $e)) { $ok = true; break; }
        }
        if (!$ok) continue;
        $files++;
        $content = @file_get_contents($path);
        if ($content === false) continue;
        foreach ($patterns as $p) {
            if (str_contains($content, $p)) {
                $this->line("[hit] $path contains '$p'");
                $hits++;
                break; // report file once
            }
        }
    }
    $this->info("Scanned $files files under $base. Hits: $hits" . ($excludeDirs ? ", skipped: $skipped" : ""));
})->purpose('Scan source files for suspicious encoding artifacts');

// Comando simple para importar catálogos (CSV / SQL)
Artisan::command('catalogos:import {--path=} {--sql=} {--fresh}', function () {
    $path = $this->option('path') ?: database_path('seeders/data');
    $sql = $this->option('sql');
    $fresh = (bool)$this->option('fresh');

    $this->info('Importando catálogos');
    $this->line('Ruta CSV: '.$path);

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

    config(['catalogos.path' => $path]);
    $this->info('Ejecutando CatalogosSeeder...');
    \Illuminate\Support\Facades\Artisan::call('db:seed', [
        '--class' => \Database\Seeders\CatalogosSeeder::class,
        '--force' => true,
    ]);
    $this->line(\Illuminate\Support\Facades\Artisan::output());

    $this->info('Importación finalizada');
    return 0;
})->purpose('Importa catálogos de municipios y secciones desde CSV/SQL con logs de progreso');

// Verificación rápida de Beneficiarios (edad, soft delete, activity log)
Artisan::command('verify:quick', function () {
    $this->info('Verificación rápida de Beneficiarios');

    $admin = \App\Models\User::where('email','admin@example.com')->first();
    if (! $admin) {
        $this->error('No existe el usuario admin@example.com');
        return 1;
    }

    // Municipio de prueba
    $mun = \App\Models\Municipio::firstOrCreate(['clave' => 9999], ['nombre' => 'Prueba']);

    // Crear beneficiario
    $b = new \App\Models\Beneficiario();
    $b->id = (string) \Illuminate\Support\Str::uuid();
    $b->folio_tarjeta = 'TEST-'.substr((string) \Illuminate\Support\Str::uuid(), 0, 8);
    $b->nombre = 'Juan';
    $b->apellido_paterno = 'Pérez';
    $b->apellido_materno = 'Lopez';
    $rand17 = strtoupper(substr(str_replace('-', '', (string) \Illuminate\Support\Str::uuid()), 0, 1));
    if (! preg_match('/[A-Z\d]/', $rand17)) { $rand17 = 'A'; }
    $rand18 = (string) random_int(0,9);
    $b->curp = 'PEPJ000101HDFLRN'.$rand17.$rand18;
    $b->fecha_nacimiento = '2000-01-01';
    $b->sexo = 'H' === 'H' ? 'M' : 'M'; // ensure a valid value
    $b->discapacidad = false;
    $b->id_ine = 'INE123';
    $b->telefono = '5512345678';
    $b->municipio_id = $mun->id;
    $b->seccional = '001';
    $b->distrito_local = 'DL-01';
    $b->distrito_federal = 'DF-01';
    $b->created_by = $admin->uuid;
    $b->save();

    $this->line('Edad calculada (esperado ~'.\Carbon\Carbon::parse('2000-01-01')->age.'): '.$b->edad);

    // Actualizar fecha para recalcular edad
    $b->fecha_nacimiento = '1990-01-01';
    $b->save();
    $this->line('Edad recalculada (esperado ~'.\Carbon\Carbon::parse('1990-01-01')->age.'): '.$b->edad);

    // Domicilio
    $d = new \App\Models\Domicilio();
    $d->id = (string) \Illuminate\Support\Str::uuid();
    $d->beneficiario_id = $b->id;
    $d->calle = 'Falsa';
    $d->numero_ext = '123';
    $d->colonia = 'Centro';
    $d->municipio = 'Prueba';
    $d->codigo_postal = '01234';
    $d->seccional = '001';
    $d->save();

    // Activity log count
    $logs = \Illuminate\Support\Facades\DB::table('activity_log')
        ->where('subject_type', \App\Models\Beneficiario::class)
        ->where('subject_id', $b->id)
        ->count();
    $this->line('Logs de actividad para beneficiario: '.$logs);

    // Soft delete
    $b->delete();
    $trashed = \App\Models\Beneficiario::withTrashed()->where('id',$b->id)->whereNotNull('deleted_at')->exists();
    $this->line('Soft delete aplicado: '.($trashed ? 'sí' : 'no'));

    $this->info('OK');
    return 0;
})->purpose('Ejecuta verificación rápida de modelo Beneficiario');
