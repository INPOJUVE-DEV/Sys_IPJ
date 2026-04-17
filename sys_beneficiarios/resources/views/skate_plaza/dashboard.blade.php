<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 m-0">Skate Plaza</h2>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">No se pudo completar la operacion solicitada. Revisa los datos e intenta nuevamente.</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4"><div class="card"><div class="card-body"><div class="text-muted">Inventario total</div><div class="h3 mb-0">{{ $stats['total'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-4"><div class="card"><div class="card-body"><div class="text-muted">Disponibles</div><div class="h3 mb-0">{{ $stats['disponibles'] }}</div></div></div></div>
        <div class="col-sm-6 col-xl-4"><div class="card"><div class="card-body"><div class="text-muted">Prestadas</div><div class="h3 mb-0">{{ $stats['prestadas'] }}</div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm h-100" id="skateSearchCard"
                data-search-url="{{ route('skate-plaza.beneficiarios.search') }}"
                data-loan-url="{{ route('skate-plaza.prestamos.store') }}"
                data-return-url-base="{{ url('/skate-plaza/prestamos') }}">
                <div class="card-header">Buscar beneficiario</div>
                <div class="card-body">
                    <form id="beneficiarioLookupForm" class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Buscar por</label>
                            <select name="tipo_busqueda" class="form-select">
                                <option value="folio_tarjeta">Numero de tarjeta</option>
                                <option value="curp">CURP</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Valor</label>
                            <input name="valor" class="form-control" placeholder="Captura el dato exacto" required>
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-primary" type="submit">Buscar</button>
                        </div>
                    </form>

                    <div id="lookupFeedback" class="small text-muted mt-3">La busqueda es exacta y solo acepta CURP o numero de tarjeta.</div>
                    <div id="beneficiarioLookupResult" class="mt-3"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header">Prestamos activos</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Proteccion</th>
                                <th>Beneficiario</th>
                                <th>Tarjeta</th>
                                <th>Prestada</th>
                                <th class="text-end">Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeLoans as $proteccion)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $proteccion->tipo }}</div>
                                        <div class="small font-monospace">{{ $proteccion->numero_inventario }}</div>
                                    </td>
                                    <td>{{ $proteccion->beneficiario?->nombre }} {{ $proteccion->beneficiario?->apellido_paterno }}</td>
                                    <td class="font-monospace">{{ $proteccion->beneficiario?->folio_tarjeta ?? 'N/D' }}</td>
                                    <td>{{ optional($proteccion->prestada_at)->format('Y-m-d H:i') }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('skate-plaza.prestamos.devolver', $proteccion) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Marcar devolucion</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No tienes protecciones prestadas actualmente.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const card = document.getElementById('skateSearchCard');
        const form = document.getElementById('beneficiarioLookupForm');
        const result = document.getElementById('beneficiarioLookupResult');
        const feedback = document.getElementById('lookupFeedback');
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const protecciones = @json($availableProtecciones->map(fn ($item) => [
            'id' => $item->id,
            'tipo' => $item->tipo,
            'numero_inventario' => $item->numero_inventario,
        ])->values());

        if (!card || !form || !result) return;

        const buildProteccionOptions = () => protecciones.map((item) => (
            `<option value="${item.id}">${item.tipo} · ${item.numero_inventario}</option>`
        )).join('');

        const renderLoanForm = (payload) => {
            if (!protecciones.length) {
                return `
                    <div class="alert alert-warning mb-0">
                        No tienes protecciones disponibles en inventario para asignar.
                    </div>
                `;
            }

            return `
                <form method="POST" action="${card.dataset.loanUrl}" class="mt-3">
                    <input type="hidden" name="_token" value="${token}">
                    <input type="hidden" name="beneficiario_id" value="${payload.id}">
                    <div class="mb-3">
                        <label class="form-label">Proteccion disponible</label>
                        <select name="proteccion_id" class="form-select" required>
                            <option value="">Selecciona</option>
                            ${buildProteccionOptions()}
                        </select>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-primary" type="submit">Registrar prestamo</button>
                    </div>
                </form>
            `;
        };

        const renderReturnForm = (loan) => {
            if (!loan?.puede_devolver) {
                return `
                    <div class="alert alert-warning mb-0">
                        El beneficiario ya tiene una proteccion bajo resguardo con ${loan?.usuario_nombre || 'otro usuario'}.
                    </div>
                `;
            }

            return `
                <form method="POST" action="${card.dataset.returnUrlBase}/${loan.proteccion_id}/devolver" class="mt-3">
                    <input type="hidden" name="_token" value="${token}">
                    <div class="text-end">
                        <button class="btn btn-outline-danger" type="submit">Marcar devolucion</button>
                    </div>
                </form>
            `;
        };

        const renderResult = (payload) => {
            const loan = payload.prestamo_activo;
            const summary = `
                <div class="card border border-secondary border-opacity-25 bg-dark-subtle">
                    <div class="card-body">
                        <div class="small text-muted">Beneficiario</div>
                        <div class="h5 mb-1">${payload.nombre_completo}</div>
                        <div class="small text-muted">Folio tarjeta: <span class="font-monospace">${payload.folio_tarjeta || 'N/D'}</span></div>
                        <div class="small text-muted">CURP: <span class="font-monospace">${payload.curp}</span></div>
                        ${loan ? `
                            <div class="alert alert-info mt-3 mb-0">
                                Tiene prestamo activo de <strong>${loan.tipo}</strong> <span class="font-monospace">${loan.numero_inventario}</span>.
                            </div>
                        ` : ''}
                        ${loan ? renderReturnForm(loan) : renderLoanForm(payload)}
                    </div>
                </div>
            `;
            result.innerHTML = summary;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const params = new URLSearchParams(new FormData(form));
            result.innerHTML = '';
            feedback.textContent = 'Buscando beneficiario...';

            try {
                const response = await fetch(`${card.dataset.searchUrl}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'No fue posible completar la busqueda.');
                }

                feedback.textContent = '';
                renderResult(payload);
            } catch (error) {
                feedback.textContent = error.message || 'No fue posible completar la busqueda.';
            }
        });
    });
    </script>
    @endpush
</x-app-layout>
