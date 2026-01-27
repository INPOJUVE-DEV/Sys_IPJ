<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'capturista']) ?? false;
    }

    public function rules(): array
    {
        return [
            'programa_id' => ['required', 'exists:programas,id'],
            'periodo' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'estatus' => ['required', Rule::in(['inscrito', 'baja', 'lista_espera'])],
        ];
    }
}
