<?php

declare(strict_types=1);

namespace Tests\Feature\Vol;

use App\Models\Beneficiario;
use App\Models\User;
use App\Models\VolEnrollment;
use App\Models\VolGroup;
use App\Models\VolPayment;
use App\Models\VolSite;
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
