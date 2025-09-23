<?php

declare(strict_types=1);

namespace Tests\Feature\Vol;

use App\Models\Beneficiario;
use App\Models\VolEnrollment;
use App\Models\VolGroup;
use App\Models\VolPayment;
use App\Models\VolSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolEnrollmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(
            \App\Models\User::factory()->create()->assignRole('admin')
        );
    }

    public function test_enrollment_requires_payment(): void
    {
        $group = $this->createGroupWithCapacity();
        $beneficiario = Beneficiario::factory()->create();

        $response = $this->postJson(route('vol.enrollments.store', $group), [
            'beneficiario_id' => $beneficiario->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('beneficiario_id');
    }

    public function test_enrollment_duplicate_same_month_is_rejected(): void
    {
        $group = $this->createGroupWithCapacity();
        $beneficiario = Beneficiario::factory()->create();

        VolPayment::factory()->create([
            'beneficiario_id' => $beneficiario->id,
            'payment_date' => now()->toDateString(),
        ]);

        $this->postJson(route('vol.enrollments.store', $group), [
            'beneficiario_id' => $beneficiario->id,
        ])->assertCreated();

        $response = $this->postJson(route('vol.enrollments.store', $group), [
            'beneficiario_id' => $beneficiario->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('beneficiario_id');
    }

    public function test_enrollment_fails_when_capacity_full(): void
    {
        $group = $this->createGroupWithCapacity(3);

        Beneficiario::factory()->count(3)->create()->each(function (Beneficiario $beneficiario) use ($group) {
            VolPayment::factory()->create([
                'beneficiario_id' => $beneficiario->id,
                'payment_date' => now()->toDateString(),
            ]);

            $this->postJson(route('vol.enrollments.store', $group), [
                'beneficiario_id' => $beneficiario->id,
            ])->assertCreated();
        });

        $newBeneficiario = Beneficiario::factory()->create();
        VolPayment::factory()->create([
            'beneficiario_id' => $newBeneficiario->id,
            'payment_date' => now()->toDateString(),
        ]);

        $this->postJson(route('vol.enrollments.store', $group), [
            'beneficiario_id' => $newBeneficiario->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('group_id');
    }

    public function test_logical_drop_frees_capacity(): void
    {
        $group = $this->createGroupWithCapacity(2);
        $beneficiarios = Beneficiario::factory()->count(2)->create();

        foreach ($beneficiarios as $beneficiario) {
            VolPayment::factory()->create([
                'beneficiario_id' => $beneficiario->id,
                'payment_date' => now()->toDateString(),
            ]);

            $this->postJson(route('vol.enrollments.store', $group), [
                'beneficiario_id' => $beneficiario->id,
            ])->assertCreated();
        }

        $enrollment = VolEnrollment::first();
        $this->deleteJson(route('vol.enrollments.destroy', $enrollment))
            ->assertOk();

        $group->refresh()->loadCount(['enrollments as active_enrollments' => fn ($q) => $q->where('status', 'inscrito')]);

        $this->assertEquals(1, $group->active_enrollments);
        $this->assertEquals(1, $group->available_slots);
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
}