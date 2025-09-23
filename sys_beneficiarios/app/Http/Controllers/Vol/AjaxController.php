<?php

namespace App\Http\Controllers\Vol;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\VolGroup;
use App\Services\Vol\CapacityGuard;
use App\Services\Vol\MonthlyEnrollmentGuard;
use App\Services\Vol\PaymentGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AjaxController extends Controller
{
    public function lookupBeneficiario(Request $request): JsonResponse
    {
        $this->ensureVolAccess($request);

        $validated = $request->validate([
            'curp' => ['required', 'string', 'max:18'],
        ]);

        $beneficiario = Beneficiario::query()
            ->where('curp', strtoupper($validated['curp']))
            ->first();

        if (! $beneficiario) {
            return response()->json(['message' => 'No se encontro el beneficiario.'], 404);
        }

        return response()->json([
            'id' => $beneficiario->id,
            'nombre' => trim(sprintf('%s %s %s', $beneficiario->nombre, $beneficiario->apellido_paterno, $beneficiario->apellido_materno)),
            'curp' => $beneficiario->curp,
        ]);
    }

    public function validateGroup(
        Request $request,
        VolGroup $group,
        PaymentGuard $paymentGuard,
        MonthlyEnrollmentGuard $monthlyGuard,
        CapacityGuard $capacityGuard
    ): JsonResponse {
        $this->ensureVolAccess($request);
        $this->authorize('view', $group);

        $validated = $request->validate([
            'beneficiario_id' => ['nullable', 'uuid', 'exists:beneficiarios,id'],
        ]);

        $beneficiarioId = $validated['beneficiario_id'] ?? $request->route('beneficiario') ?? $request->route('id');

        if (! $beneficiarioId) {
            return response()->json([
                'message' => 'Debes proporcionar un beneficiario.',
            ], 422);
        }

        $hasPayment = $paymentGuard->hasValidPayment($beneficiarioId);
        $hasCapacity = $capacityGuard->hasCapacity($group);
        $monthlyDuplicate = ! $monthlyGuard->allows($beneficiarioId, now());

        return response()->json([
            'has_payment' => $hasPayment,
            'has_capacity' => $hasCapacity,
            'monthly_duplicate' => $monthlyDuplicate,
        ]);
    }

    private function ensureVolAccess(Request $request): void
    {
        abort_unless($request->user()?->can('vol.groups.manage') || $request->user()?->can('vol.enrollments.manage'), 403);
    }
}

