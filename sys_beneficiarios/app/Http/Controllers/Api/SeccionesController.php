<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seccion;

class SeccionesController extends Controller
{
    public function show(string $seccional)
    {
        $raw = trim($seccional);
        $candidates = array_unique([
            $raw,
            ltrim($raw, '0'),
            str_pad(ltrim($raw, '0'), 4, '0', STR_PAD_LEFT),
        ]);
        $seccion = Seccion::whereIn('seccional', $candidates)->first();
        if (! $seccion) {
            abort(404);
        }

        return [
            'municipio_id' => $seccion->municipio_id,
            'distrito_local' => $seccion->distrito_local,
            'distrito_federal' => $seccion->distrito_federal,
        ];
    }
}
