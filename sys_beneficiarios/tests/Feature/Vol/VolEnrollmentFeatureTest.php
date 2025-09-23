<?php

declare(strict_types=1);

namespace Tests\Feature\Vol;

use App\Models\Beneficiario;
use App\Models\User;
use App\Models\VolEnrollment;
use App\Models\VolGroup;
use App\Models\VolPayment;
use App\Models\VolSite;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VolEnrollmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin']);
        Permission::findOrCreate('vol.enrollments.manage');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo('vol.enrollments.manage');

        Sanctum::actingAs($this->admin, abilities: ['*']);
    }

    public function test_enrollment_requires_payment(): void
    {
        $group = $this->createGroupWithCapacity();
        $beneficiario = $this->createBeneficiario();

        $response = $this->postJson($this->enrollmentStoreUrl($group), [
            'beneficiario_id' => $beneficiario->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('beneficiario_id');
    }

    public function test_enrollment_duplicate_same_month_is_rejected(): void
    {
        $group = $this->createGroupWithCapacity();
        $beneficiario = $this->createBeneficiario();

        VolPayment::factory()->create([
            'beneficiario_id' => $beneficiario->id,
            'payment_date' => now()->toDateString(),
        ]);

        $this->postJson($this->enrollmentStoreUrl($group), [
            'beneficiario_id' => $beneficiario->id,
        ])->assertCreated();

        $response = $this->postJson($this->enrollmentStoreUrl($group), [
            'beneficiario_id' => $beneficiario->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('beneficiario_id');
    }

    public function test_enrollment_fails_when_capacity_of_twelve_is_full(): void
    {
        $group = $this->createGroupWithCapacity(12);
        $beneficiarios = Beneficiario::factory()
            ->count(12)
            ->state(['created_by' => $this->admin->uuid])
            ->create();

        foreach ($beneficiarios as $beneficiario) {
            VolPayment::factory()->create([
                'beneficiario_id' => $beneficiario->id,
                'payment_date' => now()->toDateString(),
            ]);

            $this->postJson($this->enrollmentStoreUrl($group), [
                'beneficiario_id' => $beneficiario->id,
            ])->assertCreated();
        }

        $newBeneficiario = $this->createBeneficiario();
        VolPayment::factory()->create([
            'beneficiario_id' => $newBeneficiario->id,
            'payment_date' => now()->toDateString(),
        ]);

        $this->postJson($this->enrollmentStoreUrl($group), [
            'beneficiario_id' => $newBeneficiario->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('group_id');
    }

    public function test_logical_drop_frees_capacity(): void
    {
        $group = $this->createGroupWithCapacity(2);
        $beneficiarios = Beneficiario::factory()
            ->count(2)
            ->state(['created_by' => $this->admin->uuid])
            ->create();

        foreach ($beneficiarios as $beneficiario) {
            VolPayment::factory()->create([
                'beneficiario_id' => $beneficiario->id,
                'payment_date' => now()->toDateString(),
            ]);

            $this->postJson($this->enrollmentStoreUrl($group), [
                'beneficiario_id' => $beneficiario->id,
            ])->assertCreated();
        }

        $enrollment = VolEnrollment::first();
        $this->deleteJson($this->enrollmentDestroyUrl($enrollment))
            ->assertOk();

        $enrollment->refresh();
        $this->assertEquals('baja', $enrollment->status);

        $group->refresh()->loadCount([
            'enrollments as active_enrollments' => fn ($query) => $query->where('status', 'inscrito'),
        ]);

        $this->assertEquals(1, $group->active_enrollments);
        $this->assertEquals(1, $group->available_slots);
    }

    public function test_bulk_enrollment_handles_capacity_and_monthly_guards(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 3, 10, 9));

        $capital = VolSite::factory()->create([
            'name' => 'Capital',
            'state' => 'San Luis Potosí',
            'city' => 'San Luis Potosí',
            'address' => 'Av. Universidad 120',
            'active' => true,
        ]);

        $cdValles = VolSite::factory()->create([
            'name' => 'Cd. Valles',
            'state' => 'San Luis Potosí',
            'city' => 'Ciudad Valles',
            'address' => 'Calle Hidalgo 45',
            'active' => true,
        ]);

        $capitalLmv = $this->createPublishedGroupForSite($capital, [
            'name' => 'Capital LMV',
            'type' => 'semanal',
            'schedule_template' => 'lmv',
            'start_date' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        $capitalSab = $this->createPublishedGroupForSite($capital, [
            'name' => 'Capital Sabatino',
            'type' => 'sabatino',
            'schedule_template' => 'sab',
            'start_date' => Carbon::now()->addDays(12)->toDateString(),
        ]);

        $vallesLmv = $this->createPublishedGroupForSite($cdValles, [
            'name' => 'Cd. Valles LMV',
            'type' => 'semanal',
            'schedule_template' => 'lmv',
            'start_date' => Carbon::now()->addDays(8)->toDateString(),
        ]);

        $vallesSab = $this->createPublishedGroupForSite($cdValles, [
            'name' => 'Cd. Valles Sabatino',
            'type' => 'sabatino',
            'schedule_template' => 'sab',
            'start_date' => Carbon::now()->addDays(15)->toDateString(),
        ]);

        $this->assertNotEquals($capitalLmv->start_date->toDateString(), $capitalSab->start_date->toDateString());
        $this->assertNotEquals($vallesLmv->start_date->toDateString(), $vallesSab->start_date->toDateString());

        $beneficiarios = Beneficiario::factory()
            ->count(20)
            ->state(['created_by' => $this->admin->uuid])
            ->create();

        $beneficiariosWithPayments = $beneficiarios->take(15);
        $paymentTypes = ['transferencia', 'tarjeta', 'deposito'];
        $monthStart = Carbon::now()->startOfMonth();

        foreach ($beneficiariosWithPayments as $index => $beneficiario) {
            VolPayment::factory()->create([
                'beneficiario_id' => $beneficiario->id,
                'payment_type' => $paymentTypes[$index % count($paymentTypes)],
                'payment_date' => $monthStart->copy()->addDays($index)->toDateString(),
                'created_by' => $this->admin->id,
            ]);
        }

        $this->assertSame(15, VolPayment::count());
        $this->assertTrue(VolPayment::all()->every(fn (VolPayment $payment) => $payment->payment_date->isSameMonth(Carbon::now())));

        $monthlyDuplicate = $beneficiariosWithPayments->first();
        VolEnrollment::factory()->create([
            'group_id' => $capitalSab->id,
            'beneficiario_id' => $monthlyDuplicate->id,
            'status' => 'inscrito',
            'enrolled_at' => Carbon::now()->subDays(2),
            'created_by' => $this->admin->id,
        ]);

        $lmvCandidates = $beneficiariosWithPayments->skip(1)->take(14)->values();
        $responses = [];

        foreach ($lmvCandidates as $index => $beneficiario) {
            $response = $this->postJson($this->enrollmentStoreUrl($capitalLmv), [
                'beneficiario_id' => $beneficiario->id,
            ]);

            $responses[] = $response;

            if ($index < 12) {
                $response->assertCreated();
            } else {
                $response->assertStatus(422)
                    ->assertJsonValidationErrors('group_id');
            }
        }

        $this->assertCount(14, $responses);

        $capitalLmv->refresh()->loadCount([
            'enrollments as active_enrollments' => fn ($query) => $query->where('status', 'inscrito'),
        ]);

        $this->assertSame(12, $capitalLmv->active_enrollments);
        $this->assertSame(0, $capitalLmv->available_slots);

        $monthlyResponse = $this->postJson($this->enrollmentStoreUrl($capitalLmv), [
            'beneficiario_id' => $monthlyDuplicate->id,
        ]);

        $monthlyResponse->assertStatus(422)
            ->assertJsonValidationErrors('beneficiario_id');

        $this->assertSame(2, collect($responses)->filter(fn ($response) => $response->getStatusCode() === 422)->count());

        Carbon::setTestNow();
    }

    private function createGroupWithCapacity(int $capacity = 12): VolGroup
    {
        $site = VolSite::factory()->create();

        return VolGroup::factory()
            ->for($site, 'site')
            ->create([
                'capacity' => $capacity,
                'state' => 'publicado',
            ]);
    }

    private function createPublishedGroupForSite(VolSite $site, array $overrides = []): VolGroup
    {
        return VolGroup::factory()
            ->for($site, 'site')
            ->create(array_merge([
                'state' => 'publicado',
                'capacity' => 12,
                'created_by' => $this->admin->id,
            ], $overrides));
    }

    private function createBeneficiario(): Beneficiario
    {
        return Beneficiario::factory()->create([
            'created_by' => $this->admin->uuid,
        ]);
    }

    private function enrollmentStoreUrl(VolGroup $group): string
    {
        return sprintf('/api/v1/bienestar/volante/groups/%s/enrollments', $group->getKey());
    }

    private function enrollmentDestroyUrl(VolEnrollment $enrollment): string
    {
        return sprintf('/api/v1/bienestar/volante/enrollments/%s', $enrollment->getKey());
    }
}
