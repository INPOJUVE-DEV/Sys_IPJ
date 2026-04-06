<?php

namespace App\Services;

use App\Models\Municipio;
use App\Models\Seccion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CatalogoCsvSyncService
{
    public function sync(array $options = []): array
    {
        $basePath = $options['base_path'] ?? config('catalogos.path', database_path('seeders/data'));
        $municipiosFile = $options['municipios_file'] ?? config('catalogos.municipios_file', 'municipios.csv');
        $seccionesFile = $options['secciones_file'] ?? config('catalogos.secciones_file', 'secciones.csv');
        $prune = (bool) ($options['prune'] ?? config('catalogos.prune', false));
        $dryRun = (bool) ($options['dry_run'] ?? config('catalogos.dry_run', false));

        $municipiosPath = $this->resolvePath($basePath, $municipiosFile);
        $seccionesPath = $this->resolvePath($basePath, $seccionesFile);

        if (! is_file($municipiosPath)) {
            throw new RuntimeException("Archivo de municipios no encontrado: {$municipiosPath}");
        }

        if (! is_file($seccionesPath)) {
            throw new RuntimeException("Archivo de secciones no encontrado: {$seccionesPath}");
        }

        $municipiosSource = $this->prepareMunicipios($this->readCsv($municipiosPath));
        $seccionesSource = $this->prepareSecciones($this->readCsv($seccionesPath), $municipiosSource);

        $stats = [
            'dry_run' => $dryRun,
            'prune' => $prune,
            'source' => [
                'municipios_file' => $municipiosPath,
                'secciones_file' => $seccionesPath,
            ],
            'municipios' => [
                'source_total' => count($municipiosSource),
                'inserted' => 0,
                'updated' => 0,
                'deleted' => 0,
            ],
            'secciones' => [
                'source_total' => count($seccionesSource),
                'inserted' => 0,
                'updated' => 0,
                'deleted' => 0,
                'skipped' => 0,
            ],
        ];

        DB::beginTransaction();

        try {
            $municipioIdsByClave = $this->syncMunicipios($municipiosSource, $prune, $stats);
            $this->syncSecciones($seccionesSource, $municipioIdsByClave, $prune, $stats);

            if ($dryRun) {
                DB::rollBack();

                return $stats;
            }

            DB::commit();

            return $stats;
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $e;
        }
    }

    protected function syncMunicipios(array $sourceRows, bool $prune, array &$stats): array
    {
        $existing = Municipio::query()->get()->keyBy('clave');
        $seenClaves = [];
        $idsByClave = [];

        foreach ($sourceRows as $row) {
            $seenClaves[] = $row['clave'];
            $current = $existing->get($row['clave']);

            if (! $current) {
                $created = Municipio::create([
                    'clave' => $row['clave'],
                    'nombre' => $row['nombre'],
                ]);
                $idsByClave[$row['clave']] = $created->id;
                $stats['municipios']['inserted']++;
                continue;
            }

            $idsByClave[$row['clave']] = $current->id;

            if ($current->nombre !== $row['nombre']) {
                $current->update(['nombre' => $row['nombre']]);
                $stats['municipios']['updated']++;
            }
        }

        if ($prune) {
            $seenClaves = array_values(array_unique($seenClaves));
            $staleSecciones = Seccion::whereHas('municipio', fn ($query) => $query->whereNotIn('clave', $seenClaves))->count();
            if ($staleSecciones > 0) {
                Seccion::whereHas('municipio', fn ($query) => $query->whereNotIn('clave', $seenClaves))->delete();
                $stats['secciones']['deleted'] += $staleSecciones;
            }

            $deleted = Municipio::whereNotIn('clave', $seenClaves)->delete();
            $stats['municipios']['deleted'] += $deleted;
        }

        return $idsByClave;
    }

    protected function syncSecciones(array $sourceRows, array $municipioIdsByClave, bool $prune, array &$stats): void
    {
        $existing = Seccion::query()->get()->keyBy('seccional');
        $seenSecciones = [];

        foreach ($sourceRows as $row) {
            $municipioId = $municipioIdsByClave[$row['municipio_clave']] ?? null;
            if (! $municipioId) {
                $stats['secciones']['skipped']++;
                continue;
            }

            $seenSecciones[] = $row['seccional'];
            $payload = [
                'municipio_id' => $municipioId,
                'distrito_local' => $row['distrito_local'],
                'distrito_federal' => $row['distrito_federal'],
            ];

            $current = $existing->get($row['seccional']);
            if (! $current) {
                Seccion::create(['seccional' => $row['seccional']] + $payload);
                $stats['secciones']['inserted']++;
                continue;
            }

            if (
                (int) $current->municipio_id !== (int) $payload['municipio_id']
                || $current->distrito_local !== $payload['distrito_local']
                || $current->distrito_federal !== $payload['distrito_federal']
            ) {
                $current->update($payload);
                $stats['secciones']['updated']++;
            }
        }

        if ($prune) {
            $seenSecciones = array_values(array_unique($seenSecciones));
            $deleted = Seccion::whereNotIn('seccional', $seenSecciones)->delete();
            $stats['secciones']['deleted'] += $deleted;
        }
    }

    protected function prepareMunicipios(array $rows): array
    {
        $prepared = [];
        $seen = [];

        foreach ($rows as $row) {
            $clave = (int) ($row['clave'] ?? $row['id'] ?? 0);
            $sourceId = (int) ($row['id'] ?? 0);
            $nombre = $this->normalizeValue($row['nombre'] ?? '');

            if ($clave <= 0 || $nombre === '') {
                continue;
            }

            if (isset($seen[$clave])) {
                throw new RuntimeException("Municipio duplicado en CSV para clave {$clave}");
            }

            $seen[$clave] = true;
            $prepared[] = [
                'source_id' => $sourceId > 0 ? $sourceId : null,
                'clave' => $clave,
                'nombre' => $nombre,
                'nombre_key' => $this->normalizeLookupKey($nombre),
            ];
        }

        return $prepared;
    }

    protected function prepareSecciones(array $rows, array $municipiosSource): array
    {
        $prepared = [];
        $seen = [];
        $municipiosBySourceId = collect($municipiosSource)
            ->filter(fn (array $row) => ! empty($row['source_id']))
            ->keyBy('source_id');
        $municipiosByClave = collect($municipiosSource)->keyBy('clave');
        $municipiosByName = collect($municipiosSource)->keyBy('nombre_key');

        foreach ($rows as $row) {
            $rawSeccional = $this->normalizeValue($row['seccional'] ?? $row['seccion'] ?? '');
            $digits = preg_replace('/\D/', '', $rawSeccional);
            $seccional = $digits !== '' ? str_pad($digits, 4, '0', STR_PAD_LEFT) : $rawSeccional;
            if ($seccional === '') {
                continue;
            }

            if (isset($seen[$seccional])) {
                throw new RuntimeException("Seccion duplicada en CSV para {$seccional}");
            }

            $municipioClave = null;
            $candidateMunicipioId = (int) ($row['municipio_id'] ?? 0);
            $candidateMunicipioClave = (int) ($row['municipio_clave'] ?? 0);
            $candidateMunicipioNombre = $this->normalizeValue($row['municipio_nombre'] ?? $row['municipio'] ?? '');

            if ($candidateMunicipioId > 0 && $municipiosBySourceId->has($candidateMunicipioId)) {
                $municipioClave = $municipiosBySourceId->get($candidateMunicipioId)['clave'];
            } elseif ($candidateMunicipioId > 0 && $municipiosByClave->has($candidateMunicipioId)) {
                $municipioClave = $candidateMunicipioId;
            } elseif ($candidateMunicipioClave > 0 && $municipiosByClave->has($candidateMunicipioClave)) {
                $municipioClave = $candidateMunicipioClave;
            } elseif ($candidateMunicipioNombre !== '') {
                $nameKey = $this->normalizeLookupKey($candidateMunicipioNombre);
                if ($municipiosByName->has($nameKey)) {
                    $municipioClave = $municipiosByName->get($nameKey)['clave'];
                }
            }

            if (! $municipioClave) {
                throw new RuntimeException("No se pudo resolver el municipio para la seccion {$seccional}");
            }

            $seen[$seccional] = true;
            $prepared[] = [
                'seccional' => $seccional,
                'municipio_clave' => $municipioClave,
                'distrito_local' => $this->normalizeValue($row['distrito_local'] ?? $row['d_local'] ?? $row['dlocal'] ?? ''),
                'distrito_federal' => $this->normalizeValue($row['distrito_federal'] ?? $row['d_fed'] ?? $row['df'] ?? ''),
            ];
        }

        return $prepared;
    }

    protected function readCsv(string $path): array
    {
        $contents = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($contents === false || count($contents) === 0) {
            return [];
        }

        $headerLine = $this->stripBom((string) array_shift($contents));
        $delimiter = $this->detectDelimiter($headerLine);
        $rawHeaders = str_getcsv($headerLine, $delimiter);
        $headers = array_map(function ($header) {
            $header = $this->normalizeValue($header);
            $header = strtolower($header);
            $header = preg_replace('/[^a-z0-9]+/', '_', $header ?? '');

            return trim((string) $header, '_');
        }, $rawHeaders);

        $rows = [];
        foreach ($contents as $line) {
            $cols = str_getcsv((string) $line, $delimiter);
            if (count($cols) !== count($headers)) {
                $alternate = $delimiter === ';' ? ',' : ';';
                $cols = str_getcsv((string) $line, $alternate);
                if (count($cols) !== count($headers)) {
                    continue;
                }
            }

            $normalizedCols = array_map(fn ($value) => $this->normalizeValue($value), $cols);
            $rows[] = array_combine($headers, $normalizedCols);
        }

        return $rows;
    }

    protected function resolvePath(string $basePath, string $file): string
    {
        if (is_file($file)) {
            return $file;
        }

        return rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($file, DIRECTORY_SEPARATOR);
    }

    protected function detectDelimiter(string $line): string
    {
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    protected function normalizeLookupKey(string $value): string
    {
        return mb_strtoupper($this->normalizeValue($value), 'UTF-8');
    }

    protected function normalizeValue(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = $this->stripBom($value);

        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
        }

        return trim($value);
    }

    protected function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/u', '', $value) ?? $value;
    }
}
