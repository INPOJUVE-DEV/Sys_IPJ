@extends('layouts.app')

@section('content')
<div class="container py-4">
  <a href="{{ route('s360.enc360.view') }}" class="btn btn-link px-0">← Volver</a>
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <div class="fw-semibold">Administrar sesión #{{ $session->session_number }}</div>
        <div class="text-muted small">Beneficiario: {{ $beneficiarioName }}</div>
      </div>
      <div>
        <button type="button" class="btn btn-outline-primary" id="btn-edit">Editar</button>
      </div>
    </div>
    <div class="card-body">
      <form id="form" method="POST" action="{{ route('s360.enc360.sesiones.update', $session) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label">Fecha de sesión</label>
            <input type="date" class="form-control" name="session_date" value="{{ old('session_date', optional($session->session_date)->format('Y-m-d')) }}" disabled required>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Próxima cita</label>
            <input type="date" class="form-control" name="next_session_date" value="{{ old('next_session_date', optional($session->next_session_date)->format('Y-m-d')) }}" disabled>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Objetivo siguiente</label>
            <input type="text" class="form-control" name="next_objective" value="{{ old('next_objective', $session->next_objective) }}" disabled>
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-12 @if(!$session->is_first) d-none @endif">
            <label class="form-label">Motivo de consulta (1ª)</label>
            <textarea class="form-control" name="motivo_consulta" rows="2" @if(!$session->is_first) disabled @endif disabled>{{ old('motivo_consulta', $session->motivo_consulta) }}</textarea>
            <div class="mt-2 suggestion-box d-none" id="motivoSuggestions">
              <div class="form-text mb-1">Sugerencias rápidas</div>
              <div class="d-flex flex-wrap gap-2 suggestion-items"></div>
            </div>
          </div>
          <div class="col-12 col-md-6 @if(!$session->is_first) d-none @endif">
            <label class="form-label">Riesgo suicida</label>
            <select class="form-select" name="riesgo_suicida" @if(!$session->is_first) disabled @endif disabled>
              <option value="" {{ old('riesgo_suicida', $session->riesgo_suicida)===null?'selected':'' }}>—</option>
              <option value="0" {{ old('riesgo_suicida', (int)$session->riesgo_suicida)===0?'selected':'' }}>No</option>
              <option value="1" {{ old('riesgo_suicida', (int)$session->riesgo_suicida)===1?'selected':'' }}>Sí</option>
            </select>
          </div>
          <div class="col-12 col-md-6 @if(!$session->is_first) d-none @endif">
            <label class="form-label">Uso de sustancias</label>
            <select class="form-select" name="uso_sustancias" @if(!$session->is_first) disabled @endif disabled>
              <option value="" {{ old('uso_sustancias', $session->uso_sustancias)===null?'selected':'' }}>—</option>
              <option value="0" {{ old('uso_sustancias', (int)$session->uso_sustancias)===0?'selected':'' }}>No</option>
              <option value="1" {{ old('uso_sustancias', (int)$session->uso_sustancias)===1?'selected':'' }}>Sí</option>
            </select>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Notas</label>
          <textarea class="form-control" name="notes" rows="3" disabled>{{ old('notes', $session->notes) }}</textarea>
          <div class="mt-2 suggestion-box d-none" id="notesSuggestions">
            <div class="form-text mb-1">Notas sugeridas</div>
            <div class="d-flex flex-wrap gap-2 suggestion-items"></div>
          </div>
        </div>

        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-primary" type="submit" id="btn-save" disabled>Guardar cambios</button>
          <a href="{{ route('s360.enc360.view') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btn-edit');
    const save = document.getElementById('btn-save');
    const form = document.getElementById('form');
    const suggestionBoxes = document.querySelectorAll('.suggestion-box');

    const normalizeList = (values) => Array.from(new Set(values.filter(Boolean)));
    const loadStored = (key) => {
      try {
        const raw = localStorage.getItem(key);
        const parsed = JSON.parse(raw || '[]');
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        return [];
      }
    };
    const storeValue = (key, value) => {
      const existing = loadStored(key);
      if (existing.includes(value)) return;
      existing.unshift(value);
      localStorage.setItem(key, JSON.stringify(existing.slice(0, 20)));
    };

    const attachSuggestions = (fieldName, containerId, storageKey, defaults = []) => {
      const field = form?.querySelector(`[name="${fieldName}"]`);
      const container = document.getElementById(containerId);
      if (!field || !container) return;
      const itemsWrap = container.querySelector('.suggestion-items');
      const render = (term = '') => {
        const source = normalizeList([...defaults, ...loadStored(storageKey)]);
        const filtered = term ? source.filter(value => value.toLowerCase().includes(term.toLowerCase())) : source;
        if (!filtered.length) {
          itemsWrap.innerHTML = '<span class="text-muted small">Sin sugerencias disponibles.</span>';
          return;
        }
        itemsWrap.innerHTML = filtered.slice(0, 6).map(value => {
          const encoded = value.replace(/"/g, '&quot;');
          return `<button type="button" class="btn btn-sm btn-outline-light" data-value="${encoded}">${value}</button>`;
        }).join('');
      };
      itemsWrap.addEventListener('click', (event) => {
        const target = event.target.closest('button[data-value]');
        if (!target) return;
        field.value = target.getAttribute('data-value');
        field.dispatchEvent(new Event('input'));
      });
      field.addEventListener('input', () => render(field.value));
      form?.addEventListener('submit', () => {
        const value = field.value.trim();
        if (value) {
          storeValue(storageKey, value);
        }
      });
      render();
    };

    const defaultMotives = [
      'Evaluación inicial',
      'Seguimiento emocional',
      'Situación familiar',
    ];
    const defaultNotes = [
      'Se asignan ejercicios de respiración consciente.',
      'Se acuerda seguimiento en próxima sesión.',
      'Se refuerzan estrategias de afrontamiento.',
    ];

    attachSuggestions('motivo_consulta', 'motivoSuggestions', 'enc360_motivo_suggestions', defaultMotives);
    attachSuggestions('notes', 'notesSuggestions', 'enc360_notes_suggestions', defaultNotes);

    if (btn) {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#form [name], #form textarea, #form select').forEach(el => {
          const name = el.getAttribute('name');
          if (name === '_token' || name === '_method') return;
          el.removeAttribute('disabled');
        });
        save?.removeAttribute('disabled');
        btn.setAttribute('disabled', 'disabled');
        suggestionBoxes.forEach(box => box.classList.remove('d-none'));
      });
    }
  });
</script>
@endpush
@endsection

