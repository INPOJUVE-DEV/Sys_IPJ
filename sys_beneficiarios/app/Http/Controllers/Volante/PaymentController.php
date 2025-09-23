<?php

namespace App\Http\Controllers\Volante;

use App\Http\Controllers\Controller;
use App\Models\VolPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', VolPayment::class);

        $payments = VolPayment::query()
            ->when($request->filled('beneficiario_id'), fn ($query) => $query->where('beneficiario_id', $request->input('beneficiario_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('payment_date', '>=', Carbon::parse($request->input('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('payment_date', '<=', Carbon::parse($request->input('date_to'))))
            ->orderByDesc('payment_date')
            ->paginate($request->integer('per_page', 15));

        return response()->json($payments);
    }

    public function store(Request $request)
    {
        $this->authorize('create', VolPayment::class);

        $data = $request->validate([
            'beneficiario_id' => ['required', 'string', 'uuid', 'exists:beneficiarios,id'],
            'payment_type' => ['required', Rule::in(['transferencia', 'tarjeta', 'deposito'])],
            'payment_date' => ['required', 'date'],
            'receipt_ref' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = VolPayment::create([
            'beneficiario_id' => $data['beneficiario_id'],
            'payment_type' => $data['payment_type'],
            'payment_date' => $data['payment_date'],
            'receipt_ref' => $data['receipt_ref'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return response()->json(['data' => $payment], 201);
    }
}