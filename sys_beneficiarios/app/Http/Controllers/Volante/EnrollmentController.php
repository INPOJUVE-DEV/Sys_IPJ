<?php

namespace App\Http\Controllers\Volante;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrollmentRequest;
use App\Models\VolEnrollment;
use App\Models\VolGroup;
use App\Services\Vol\CapacityGuard;
use App\Services\Vol\MonthlyEnrollmentGuard;
use App\Services\Vol\PaymentGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

    public function index(VolGroup $group, Request $request)
    {
        $this->authorize('view', $group);
        $this->authorize('viewAny', VolEnrollment::class);

        $enrollments = $group->enrollments()
            ->with(['beneficiario'])
            ->orderByDesc('enrolled_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($enrollments);
    }

    public function store(EnrollmentRequest $request, VolGroup $group)
    {
        $this->authorize('create', VolEnrollment::class);
        $this->authorize('view', $group);

        if ($group->state === 'cerrado') {
            throw ValidationException::withMessages([
                'group_id' => ['El grupo esta cerrado y no admite nuevas inscripciones.'],
            ]);
        }

        $beneficiarioId = (string) $request->validated()['beneficiario_id'];
        $enrolledAt = Carbon::now();

        if ($group->enrollments()->where('beneficiario_id', $beneficiarioId)->where('status', 'inscrito')->exists()) {
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

        $createdBy = $request->input('created_by', Auth::id());

        $enrollment = DB::transaction(function () use ($group, $beneficiarioId, $enrolledAt, $createdBy) {
            $enrollment = new VolEnrollment([
                'group_id' => $group->id,
                'beneficiario_id' => $beneficiarioId,
                'status' => 'inscrito',
                'enrolled_at' => $enrolledAt,
                'created_by' => $createdBy,
            ]);
            $enrollment->save();

            return $enrollment;
        });

        if (function_exists('activity')) {
            activity('vol_enrollments')
                ->causedBy($request->user())
                ->performedOn($enrollment)
                ->withProperties([
                    'group_id' => $group->id,
                    'beneficiario_id' => $beneficiarioId,
                    'enrolled_at' => $enrolledAt->toDateTimeString(),
                ])->log('inscripcion_creada');
        }

        return response()->json([
            'data' => $enrollment->fresh(['group', 'beneficiario']),
        ], 201);
    }

    public function destroy(Request $request, VolEnrollment $enrollment)
    {
        $this->authorize('delete', $enrollment);

        $data = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $enrollment->status = 'baja';
        $enrollment->unenrolled_at = Carbon::now();
        $enrollment->reason = $data['reason'] ?? null;
        $enrollment->save();

        if (function_exists('activity')) {
            activity('vol_enrollments')
                ->causedBy($request->user())
                ->performedOn($enrollment)
                ->withProperties([
                    'group_id' => $enrollment->group_id,
                    'beneficiario_id' => $enrollment->beneficiario_id,
                    'reason' => $enrollment->reason,
                ])->log('inscripcion_baja');
        }

        return response()->json(['data' => $enrollment]);
    }
}