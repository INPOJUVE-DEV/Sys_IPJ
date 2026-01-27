<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Nuevo programa</h2></x-slot>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('programas.store') }}" novalidate>
                @csrf

                @include('programas.partials.form')

                <div class="d-flex justify-content-end mt-3">
                    <a href="{{ route('programas.index') }}" class="btn btn-outline-secondary me-2"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                    <button class="btn btn-cta" type="submit"><i class="bi bi-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
