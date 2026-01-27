<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgramaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('programas', 'slug')],
            'tipo_periodo' => ['required', 'string', Rule::in(['mensual', 'unico', 'anual'])],
            'renovable' => ['required', 'boolean'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
