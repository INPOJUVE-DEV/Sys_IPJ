<?php

namespace App\Http\Requests;

use App\Models\VolEnrollment;
use Illuminate\Foundation\Http\FormRequest;

class EnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', VolEnrollment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'beneficiario_id' => ['required', 'string', 'uuid', 'exists:beneficiarios,id'],
        ];
    }
}
