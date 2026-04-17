<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Municipio;
use App\Models\Oficina;
use App\Models\Tarjeta;
use Illuminate\Http\Request;

class OficinaController extends Controller
{
    public function index()
    {
        $offices = Oficina::withCount([
            'users',
            'municipios',
            'tarjetas',
        ])->orderByRaw("CASE tipo WHEN 'central' THEN 0 ELSE 1 END")->orderBy('nombre')->get();

        $cardsByOffice = Tarjeta::query()
            ->selectRaw('oficina_id, estatus, COUNT(*) as total')
            ->groupBy('oficina_id', 'estatus')
            ->get()
            ->groupBy('oficina_id');

        $municipios = Municipio::with('oficina')->orderBy('nombre')->get();

        return view('admin.oficinas.index', compact('offices', 'cardsByOffice', 'municipios'));
    }

    public function assignMunicipio(Request $request, Municipio $municipio)
    {
        $data = $request->validate([
            'oficina_id' => ['nullable', 'exists:oficinas,id'],
        ]);

        $municipio->oficina_id = $data['oficina_id'] ?? null;
        $municipio->save();

        return redirect()->route('admin.oficinas.index')->with('status', 'Municipio asignado correctamente');
    }
}
