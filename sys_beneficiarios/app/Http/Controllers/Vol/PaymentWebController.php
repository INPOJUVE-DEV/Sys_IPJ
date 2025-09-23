<?php

namespace App\Http\Controllers\Vol;

use App\Http\Controllers\Controller;
use App\Models\VolPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PaymentWebController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', VolPayment::class);

        return view('vol.payments.create', [
            'lookupUrl' => route('vol.ajax.beneficiarios.lookup'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', VolPayment::class);

        $data = $request->validate([
            'beneficiario_id' => ['required', 'uuid', 'exists:beneficiarios,id'],
            'payment_type' => ['required', 'in:transferencia,tarjeta,deposito'],
            'payment_date' => ['required', 'date'],
            'receipt_ref' => ['nullable', 'string', 'max:255'],
        ]);

        VolPayment::create([
            'beneficiario_id' => $data['beneficiario_id'],
            'payment_type' => $data['payment_type'],
            'payment_date' => $data['payment_date'],
            'receipt_ref' => $data['receipt_ref'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('vol.payments.create')
            ->with('status', 'Pago registrado correctamente.');
    }
}

