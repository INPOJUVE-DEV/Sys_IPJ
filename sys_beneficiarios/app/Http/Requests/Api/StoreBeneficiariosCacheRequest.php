<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\StoreBeneficiarioRequest;
use App\Models\Beneficiario;
use Illuminate\Foundation\Http\FormRequest;

class StoreBeneficiariosCacheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Beneficiario::class) ?? false;
    }

    public function rules(): array
    {
        $baseRules = (new StoreBeneficiarioRequest())->rules();
        $rules = [
            'beneficiarios' => ['required', 'array', 'min:1'],
            'source' => ['nullable', 'string', 'max:255'],
        ];

        foreach ($baseRules as $field => $rule) {
            $rules['beneficiarios.*.' . $field] = $rule;
        }

        $rules['beneficiarios.*.curp'][] = 'distinct';
        $rules['beneficiarios.*.folio_tarjeta'][] = 'distinct';

        return $rules;
    }
}
