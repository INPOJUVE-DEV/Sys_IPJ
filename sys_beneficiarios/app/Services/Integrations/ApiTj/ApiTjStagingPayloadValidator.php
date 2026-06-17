<?php

namespace App\Services\Integrations\ApiTj;

use App\Rules\ValidSeccional;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ApiTjStagingPayloadValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload): array
    {
        $curpRegex = '/^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/i';

        if (isset($payload['source'])) {
            $payload['source'] = strtolower(trim((string) $payload['source']));
        }

        if (isset($payload['beneficiario']['sexo'])) {
            $payload['beneficiario']['sexo'] = strtoupper(trim((string) $payload['beneficiario']['sexo']));
        }

        $validated = Validator::make($payload, [
            'external_request_id' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', Rule::in(['api_tj'])],
            'submitted_by' => ['nullable', 'string', 'max:255'],
            'beneficiario' => ['required', 'array'],
            'beneficiario.folio_tarjeta' => ['nullable', 'string', 'max:255'],
            'beneficiario.nombre' => ['required', 'string', 'max:255'],
            'beneficiario.apellido_paterno' => ['required', 'string', 'max:255'],
            'beneficiario.apellido_materno' => ['required', 'string', 'max:255'],
            'beneficiario.curp' => ['required', 'string', 'size:18', 'regex:'.$curpRegex],
            'beneficiario.fecha_nacimiento' => ['required', 'date'],
            'beneficiario.sexo' => ['required', Rule::in(['M', 'F', 'X'])],
            'beneficiario.discapacidad' => ['required', 'boolean'],
            'beneficiario.id_ine' => ['required', 'string', 'max:255'],
            'beneficiario.telefono' => ['required', 'regex:/^\d{10}$/'],
            'beneficiario.domicilio' => ['required', 'array'],
            'beneficiario.domicilio.calle' => ['required', 'string', 'max:255'],
            'beneficiario.domicilio.numero_ext' => ['required', 'string', 'max:50'],
            'beneficiario.domicilio.numero_int' => ['nullable', 'string', 'max:50'],
            'beneficiario.domicilio.colonia' => ['required', 'string', 'max:255'],
            'beneficiario.domicilio.municipio_id' => ['required', 'exists:municipios,id'],
            'beneficiario.domicilio.codigo_postal' => ['required', 'string', 'max:20'],
            'beneficiario.domicilio.seccional' => ['required', 'string', 'max:255', new ValidSeccional()],
        ])->validate();

        Arr::set($validated, 'source', 'api_tj');
        Arr::set($validated, 'submitted_by', $this->nullableTrimmedString(Arr::get($validated, 'submitted_by')));
        Arr::set($validated, 'beneficiario.curp', strtoupper(trim((string) Arr::get($validated, 'beneficiario.curp'))));
        Arr::set($validated, 'beneficiario.nombre', trim((string) Arr::get($validated, 'beneficiario.nombre')));
        Arr::set($validated, 'beneficiario.apellido_paterno', trim((string) Arr::get($validated, 'beneficiario.apellido_paterno')));
        Arr::set($validated, 'beneficiario.apellido_materno', trim((string) Arr::get($validated, 'beneficiario.apellido_materno')));
        Arr::set($validated, 'beneficiario.sexo', strtoupper(trim((string) Arr::get($validated, 'beneficiario.sexo'))));
        Arr::set($validated, 'beneficiario.id_ine', trim((string) Arr::get($validated, 'beneficiario.id_ine')));
        Arr::set($validated, 'beneficiario.telefono', trim((string) Arr::get($validated, 'beneficiario.telefono')));
        Arr::set($validated, 'beneficiario.discapacidad', (bool) Arr::get($validated, 'beneficiario.discapacidad'));
        Arr::set($validated, 'beneficiario.folio_tarjeta', $this->nullableTrimmedString(Arr::get($validated, 'beneficiario.folio_tarjeta')));
        Arr::set($validated, 'beneficiario.domicilio.calle', trim((string) Arr::get($validated, 'beneficiario.domicilio.calle')));
        Arr::set($validated, 'beneficiario.domicilio.numero_ext', trim((string) Arr::get($validated, 'beneficiario.domicilio.numero_ext')));
        Arr::set($validated, 'beneficiario.domicilio.numero_int', $this->nullableTrimmedString(Arr::get($validated, 'beneficiario.domicilio.numero_int')));
        Arr::set($validated, 'beneficiario.domicilio.colonia', trim((string) Arr::get($validated, 'beneficiario.domicilio.colonia')));
        Arr::set($validated, 'beneficiario.domicilio.codigo_postal', trim((string) Arr::get($validated, 'beneficiario.domicilio.codigo_postal')));
        Arr::set($validated, 'beneficiario.domicilio.seccional', trim((string) Arr::get($validated, 'beneficiario.domicilio.seccional')));

        return $validated;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed !== '' ? $trimmed : null;
    }
}
