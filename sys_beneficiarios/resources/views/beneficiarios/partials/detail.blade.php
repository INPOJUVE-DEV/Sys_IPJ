@php($d = $beneficiario->domicilio)
<dl class="row mb-0">
    <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8">{{ $beneficiario->nombre }} {{ $beneficiario->apellido_paterno }} {{ $beneficiario->apellido_materno }}</dd>
    <dt class="col-sm-4">CURP</dt><dd class="col-sm-8">{{ $beneficiario->curp }}</dd>
    <dt class="col-sm-4">Fecha nac.</dt><dd class="col-sm-8">{{ $beneficiario->fecha_nacimiento?->format('Y-m-d') }} ({{ $beneficiario->edad }} años)</dd>
    <dt class="col-sm-4">Sexo</dt><dd class="col-sm-8">{{ $beneficiario->sexo }}</dd>
    <dt class="col-sm-4">Discapacidad</dt><dd class="col-sm-8">{{ $beneficiario->discapacidad ? 'Sí' : 'No' }}</dd>
    <dt class="col-sm-4">Teléfono</dt><dd class="col-sm-8">{{ $beneficiario->telefono }}</dd>
    <dt class="col-sm-4">Folio tarjeta</dt><dd class="col-sm-8">{{ $beneficiario->folio_tarjeta }}</dd>
    <dt class="col-sm-4">Municipio</dt><dd class="col-sm-8">{{ optional($beneficiario->municipio)->nombre }}</dd>
    <dt class="col-sm-4">Seccional</dt><dd class="col-sm-8">{{ $beneficiario->seccional }}</dd>
    <dt class="col-sm-4">Dirección</dt><dd class="col-sm-8">{{ $d?->calle }} {{ $d?->numero_ext }} {{ $d?->numero_int ? 'Int '.$d->numero_int : '' }}, {{ $d?->colonia }}, CP {{ $d?->codigo_postal }}</dd>
</dl>

