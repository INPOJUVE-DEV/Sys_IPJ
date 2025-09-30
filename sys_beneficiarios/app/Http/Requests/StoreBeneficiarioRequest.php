<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Seccion;

class StoreBeneficiarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Beneficiario::class) ?? false;
    }

    public function rules(): array
    {
        $curpRegex = '/^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/i';

        $seccionalExists = function (string $attribute, $value, \Closure $fail) {
            $raw = trim((string)($value ?? ''));
            if ($raw === '') return; // 'required' lo captura
            $candidates = array_unique([
                $raw,
                ltrim($raw, '0'),
                str_pad(ltrim($raw, '0'), 4, '0', STR_PAD_LEFT),
            ]);
            if (! Seccion::whereIn('seccional', $candidates)->exists()) {
                $fail('La seccional no existe en el catálogo de secciones');
            }
        };

        return [
            'folio_tarjeta' => ['required','string','max:255', 'unique:beneficiarios,folio_tarjeta'],
            'nombre' => ['required','string','max:255'],
            'apellido_paterno' => ['required','string','max:255'],
            'apellido_materno' => ['required','string','max:255'],
            'curp' => ['required','string','size:18', 'regex:'.$curpRegex, 'unique:beneficiarios,curp'],
            'fecha_nacimiento' => ['required','date'],
            'sexo' => ['required', Rule::in(['M','F','X'])],
            'discapacidad' => ['required','boolean'],
            'id_ine' => ['required','string','max:255'],
            'telefono' => ['required','regex:/^\d{10}$/'],

            // Domicilio: requeridos todos menos numero_int
            'domicilio.calle' => ['required','string','max:255'],
            'domicilio.numero_ext' => ['required','string','max:50'],
            'domicilio.numero_int' => ['nullable','string','max:50'],
            'domicilio.colonia' => ['required','string','max:255'],
            'domicilio.municipio_id' => ['required','exists:municipios,id'],
            'domicilio.codigo_postal' => ['required','string','max:20'],
            'domicilio.seccional' => ['required','string','max:255', $seccionalExists],
        ];
    }
}
