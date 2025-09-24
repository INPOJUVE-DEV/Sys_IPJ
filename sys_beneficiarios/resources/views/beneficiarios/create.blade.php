<x-app-layout>
    <x-slot name="header"><h2 class="h4 m-0">Nuevo beneficiario</h2></x-slot>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('beneficiarios.store') }}" novalidate>
                @csrf

                @include('beneficiarios.partials.form', ['mode' => 'create'])

                <div class="d-flex justify-content-end mt-3">
                    <a href="{{ route('beneficiarios.index') }}" class="btn btn-outline-secondary me-2"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
                    <button class="btn btn-cta" type="submit"><i class="bi bi-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
    @if(session('beneficiario_registered'))
        @push('scripts')
        <script>window.beneficiarioWizardShouldReset = true;</script>
        @endpush
    @endif
    @push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const curpInput = document.querySelector('input[name="curp"]');
  const fechaInput = document.querySelector('input[name="fecha_nacimiento"]');
  const sexoSelect = document.querySelector('select[name="sexo"]');
  const edadInput = document.querySelector('input[name="edad"]');

  function parseCurp(curp) {
    if (!curp || curp.length < 11) return null;
    // CURP: YYYY positions: 5-10 (1-based) => indices 4..9 (0-based)
    const yymmdd = curp.substring(4, 10);
    const sex = curp[10]?.toUpperCase();
    const yy = parseInt(yymmdd.substring(0,2), 10);
    const mm = yymmdd.substring(2,4);
    const dd = yymmdd.substring(4,6);
    if (isNaN(yy)) return null;
    const currentYY = new Date().getFullYear() % 100;
    const year = yy > currentYY ? 1900 + yy : 2000 + yy;
    const iso = `${year}-${mm}-${dd}`;
    return { date: iso, sex };
  }

  function updateAge(isoDate) {
    if (!isoDate || !edadInput) return;
    const birth = new Date(isoDate);
    if (isNaN(birth.getTime())) return;
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    edadInput.value = age;
  }

  function applyFromCurp() {
    const curp = curpInput?.value?.trim().toUpperCase();
    if (!curp) return;
    if (curp.length !== 18) {
      if (curpInput.reportValidity) {
        curpInput.setCustomValidity('La CURP debe tener 18 caracteres.');
        curpInput.reportValidity();
      }
      return;
    } else {
      curpInput.setCustomValidity('');
    }
    const parsed = parseCurp(curp);
    if (!parsed) return;
    if (fechaInput) {
      fechaInput.value = parsed.date;
      updateAge(parsed.date);
    }
    if (sexoSelect && (parsed.sex === 'H' || parsed.sex === 'M')) {
      sexoSelect.value = parsed.sex === 'H' ? 'M' : 'F';
    }
  }

  curpInput?.addEventListener('blur', applyFromCurp);
  curpInput?.addEventListener('input', () => { if (curpInput.value.length === 18) applyFromCurp(); });
  fechaInput?.addEventListener('change', (e) => updateAge(e.target.value));
});
</script>
@endpush
</x-app-layout>
