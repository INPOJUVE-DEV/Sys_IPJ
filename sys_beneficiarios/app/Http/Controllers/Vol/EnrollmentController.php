<?php

namespace App\Http\Controllers\Vol;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrollmentRequest;
use App\Models\VolEnrollment;
use App\Models\VolGroup;
use App\Services\Vol\CapacityGuard;
use App\Services\Vol\MonthlyEnrollmentGuard;
use App\Services\Vol\PaymentGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly PaymentGuard $paymentGuard,
        private readonly MonthlyEnrollmentGuard $monthlyGuard,
        private readonly CapacityGuard $capacityGuard,
    ) {
    }

    public function store(EnrollmentRequest $request, VolGroup $group)
    {
        $this->authorize('create', VolEnrollment::class);

        $beneficiarioId = (string) $request->validated()['beneficiario_id'];
        $enrolledAt = Carbon::now();

        if ($group->enrollments()->where('beneficiario_id', $beneficiarioId)->exists()) {
            throw ValidationException::withMessages([
                'beneficiario_id' => ['El beneficiario ya esta inscrito en este grupo.'],
            ]);
        }

        if (! $this->paymentGuard->hasValidPayment($beneficiarioId)) {
            throw ValidationException::withMessages([
                'beneficiario_id' => ['El beneficiario no cuenta con un pago valido.'],
            ]);
        }

        if (! $this->monthlyGuard->allows($beneficiarioId, $enrolledAt)) {
            throw ValidationException::withMessages([
                'beneficiario_id' => ['El beneficiario ya tiene una inscripcion registrada este mes.'],
            ]);
        }

        if (! $this->capacityGuard->hasCapacity($group)) {
            throw ValidationException::withMessages([
                'group_id' => ['El grupo no tiene cupo disponible.'],
            ]);
        }

        $enrollment = DB::transaction(function () use ($group, $beneficiarioId, $enrolledAt) {
            $enrollment = new VolEnrollment([
                'group_id' => $group->id,
                'beneficiario_id' => $beneficiarioId,
                'status' => 'inscrito',
                'enrolled_at' => $enrolledAt,
                'created_by' => Auth::id(),
            ]);
            $enrollment->save();

            return $enrollment;
        });

        return response()->json([
            'data' => $enrollment->fresh([
                'group',
                'beneficiario',
            ]),
        ], 201);
    }
}