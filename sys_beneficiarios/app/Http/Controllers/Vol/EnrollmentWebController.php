<?php

namespace App\Http\Controllers\Vol;

use App\Http\Controllers\Controller;
use App\Models\VolEnrollment;
use App\Models\VolGroup;
use App\Services\Vol\CapacityGuard;
use App\Services\Vol\MonthlyEnrollmentGuard;
use App\Services\Vol\PaymentGuard;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EnrollmentWebController extends Controller
{
    public function __construct(
        private readonly PaymentGuard $paymentGuard,
        private readonly MonthlyEnrollmentGuard $monthlyGuard,
        private readonly CapacityGuard $capacityGuard,
    ) {
    }

    public function create(VolGroup $group): View
    {
        $this->authorize('view', $group);

        $lookupUrl = route('vol.ajax.beneficiarios.lookup');
        $validationUrl = route('vol.ajax.groups.validate', $group);

        $activeCount = $group->enrollments()->where('status', 'inscrito')->count();

        return view('vol.enrollments.assign', [
            'group' => $group->load('site'),
            'activeEnrollments' => $activeCount,
            'lookupUrl' => $lookupUrl,
            'validationUrl' => $validationUrl,
        ]);
    }

    public function store(Request $request, VolGroup $group): RedirectResponse
    {
        $this->authorize('create', VolEnrollment::class);
        $this->authorize('view', $group);

        $data = $request->validate([
            'beneficiario_id' => ['required', 'uuid', 'exists:beneficiarios,id'],
        ]);

        $beneficiarioId = (string) $data['beneficiario_id'];
        $enrolledAt = Carbon::now();

        if ($group->state === 'cerrado') {
            throw ValidationException::withMessages([
                'beneficiario_id' => 'El grupo esta cerrado y no admite nuevas inscripciones.',
            ]);
        }

        if ($group->enrollments()->where('beneficiario_id', $beneficiarioId)->where('status', 'inscrito')->exists()) {
            throw ValidationException::withMessages([
                'beneficiario_id' => 'El beneficiario ya esta inscrito en este grupo.',
            ]);
        }

        if (! $this->paymentGuard->hasValidPayment($beneficiarioId)) {
            throw ValidationException::withMessages([
                'beneficiario_id' => 'El beneficiario no cuenta con un pago valido.',
            ]);
        }

        if (! $this->monthlyGuard->allows($beneficiarioId, $enrolledAt)) {
            throw ValidationException::withMessages([
                'beneficiario_id' => 'El beneficiario ya tiene una inscripcion registrada este mes.',
            ]);
        }

        if (! $this->capacityGuard->hasCapacity($group)) {
            throw ValidationException::withMessages([
                'beneficiario_id' => 'El grupo no tiene cupo disponible.',
            ]);
        }

        $enrollment = new VolEnrollment([
            'group_id' => $group->id,
            'beneficiario_id' => $beneficiarioId,
            'status' => 'inscrito',
            'enrolled_at' => $enrolledAt,
            'created_by' => Auth::id(),
        ]);
        $enrollment->save();

        return redirect()
            ->route('vol.groups.show', $group)
            ->with('status', 'Inscripcion registrada correctamente.');
    }

    public function destroy(Request $request, VolEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('delete', $enrollment);

        $enrollment->status = 'baja';
        $enrollment->unenrolled_at = Carbon::now();
        $enrollment->reason = $request->string('reason', '');
        $enrollment->save();

        return redirect()
            ->back()
            ->with('status', 'Inscripcion dada de baja.');
    }
}

