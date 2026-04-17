<?php

namespace App\Http\Requests;

use App\Models\Evento;
use App\Models\Municipio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Evento::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'evento_tipo_id' => [
                'required',
                'integer',
                Rule::exists('evento_tipos', 'id')->where('activo', true),
            ],
            'municipio_id' => ['required', 'integer', Rule::exists('municipios', 'id')],
            'descripcion' => ['required', 'string', 'max:5000'],
            'lugar' => ['required', 'string', 'max:255'],
            'rol_participacion' => ['required', 'string', Rule::in(array_keys(Evento::rolesParticipacion()))],
            'total_asistentes' => ['required', 'integer', 'min:0'],
            'evidencia_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateDelegadoMunicipio($validator);
        });
    }

    protected function validateDelegadoMunicipio(Validator $validator): void
    {
        $user = $this->user();
        if (! $user?->hasRole('delegado')) {
            return;
        }

        if (! $user->oficina_id) {
            $validator->errors()->add('municipio_id', 'Tu usuario no tiene region asignada.');
            return;
        }

        $municipio = Municipio::find($this->input('municipio_id'));
        if (! $municipio || (int) $municipio->oficina_id !== (int) $user->oficina_id) {
            $validator->errors()->add('municipio_id', 'El municipio seleccionado no pertenece a tu region.');
        }
    }
}
