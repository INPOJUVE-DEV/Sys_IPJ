<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SeccionResolver;

class SeccionesController extends Controller
{
    public function show(string $seccional)
    {
        $seccion = SeccionResolver::resolve($seccional)?->loadMissing('municipio');
        if (! $seccion) {
            abort(404);
        }

        return [
            'id' => $seccion->id,
            'seccional' => $seccion->seccional,
            'municipio_id' => $seccion->municipio_id,
            'municipio' => optional($seccion->municipio)->nombre,
            'distrito_local' => $seccion->distrito_local,
            'distrito_federal' => $seccion->distrito_federal,
        ];
    }
}
