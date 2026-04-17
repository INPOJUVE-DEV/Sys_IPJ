<?php

namespace App\Http\Requests;

use App\Models\EventoTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventoTipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        $eventoTipo = $this->route('eventoTipo');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('evento_tipos', 'nombre')->ignore($eventoTipo instanceof EventoTipo ? $eventoTipo->id : null),
            ],
            'activo' => ['required', 'boolean'],
        ];
    }
}
