<?php

return [
    'path' => env('CATALOGOS_PATH', database_path('seeders/data')),
    'municipios_file' => env('CATALOGOS_MUNICIPIOS_FILE', 'municipios.csv'),
    'secciones_file' => env('CATALOGOS_SECCIONES_FILE', 'secciones.csv'),
    'prune' => (bool) env('CATALOGOS_PRUNE', false),
    'dry_run' => (bool) env('CATALOGOS_DRY_RUN', false),
];
