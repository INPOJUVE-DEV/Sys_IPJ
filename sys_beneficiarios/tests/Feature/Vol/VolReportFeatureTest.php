<?php

declare(strict_types=1);

namespace Tests\Feature\Vol;

use App\Models\Beneficiario;
use App\Models\User;
use App\Models\VolEnrollment;
use App\Models\VolGroup;
use App\Models\VolSite;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VolReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin']);
        Permission::findOrCreate('vol.reports.view');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo('vol.reports.view');

        Sanctum::actingAs($this->admin, abilities: ['*']);

        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', function (?string $value, string $format): ?string {
            if ($value === null) {
                return null;
            }

            $carbon = Carbon::parse($value);

            $phpFormat = strtr($format, [
                '%Y' => 'Y',
                '%m' => 'm',
                '%d' => 'd',
                '%H' => 'H',
                '%i' => 'i',
                '%s' => 's',
            ]);

            return $carbon->format($phpFormat);
        });
    }

    public function test_monthly_report_returns_expected_counts(): void
    {
        $siteA = VolSite::factory()->create(['name' => 'Centro Norte']);
        $siteB = VolSite::factory()->create(['name' => 'Centro Sur']);

        $groupA = $this->createGroupForSite($siteA, ['name' => 'Grupo A']);
        $groupB = $this->createGroupForSite($siteB, ['name' => 'Grupo B']);

        Carbon::setTestNow(Carbon::create(2025, 1, 15, 10));

        $this->createEnrollmentForReport($groupA, Carbon::create(2025, 1, 5, 9));
        $this->createEnrollmentForReport($groupA, Carbon::create(2025, 1, 10, 9));
        $this->createEnrollmentForReport($groupB, Carbon::create(2025, 1, 12, 9));
        $this->createEnrollmentForReport($groupB, Carbon::create(2025, 1, 18, 9));
        $this->createEnrollmentForReport($groupB, Carbon::create(2025, 1, 25, 9));

        // Outside the requested month or inactive records should not count
        $this->createEnrollmentForReport($groupA, Carbon::create(2024, 12, 28, 9));
        $this->createEnrollmentForReport($groupB, Carbon::create(2025, 1, 8, 9), 'baja');

        $response = $this->getJson('/api/v1/bienestar/volante/reports/monthly?month=2025-01');

        Carbon::setTestNow();

        $response->assertOk();

        $data = $response->json();

        $this->assertSame('2025-01', $data['period']);
        $this->assertSame(5, $data['total']);

        $perSite = collect($data['per_site'])->pluck('total', 'site_id');
        $this->assertSame(2, $perSite[$siteA->id]);
        $this->assertSame(3, $perSite[$siteB->id]);

        $perGroup = collect($data['per_group'])->pluck('total', 'group_id');
        $this->assertSame(2, $perGroup[$groupA->id]);
        $this->assertSame(3, $perGroup[$groupB->id]);
    }

    public function test_quarterly_report_returns_expected_counts(): void
    {
        $site = VolSite::factory()->create();
        $group = $this->createGroupForSite($site, ['name' => 'Grupo Q1']);

        $this->createEnrollmentForReport($group, Carbon::create(2025, 1, 4, 8));
        $this->createEnrollmentForReport($group, Carbon::create(2025, 2, 14, 11));
        $this->createEnrollmentForReport($group, Carbon::create(2025, 3, 3, 15));

        // Not part of the quarter or inactive
        $this->createEnrollmentForReport($group, Carbon::create(2025, 4, 2, 9));
        $this->createEnrollmentForReport($group, Carbon::create(2025, 2, 20, 9), 'baja');

        $response = $this->getJson('/api/v1/bienestar/volante/reports/quarterly?year=2025&q=1');

        $response->assertOk();

        $data = $response->json();

        $this->assertSame(3, $data['total']);
        $this->assertSame('2025-01-01', $data['start']);
        $this->assertSame('2025-03-31', $data['end']);

        $perMonth = collect($data['per_month'])->pluck('total', 'period');
        $this->assertSame(1, $perMonth['2025-01']);
        $this->assertSame(1, $perMonth['2025-02']);
        $this->assertSame(1, $perMonth['2025-03']);

        $perSite = collect($data['per_site'])->pluck('total', 'site_id');
        $this->assertSame(3, $perSite[$site->id]);
    }

    private function createGroupForSite(VolSite $site, array $overrides = []): VolGroup
    {
        return VolGroup::factory()
            ->for($site, 'site')
            ->create(array_merge([
                'state' => 'publicado',
                'capacity' => 20,
            ], $overrides));
    }

    private function createEnrollmentForReport(VolGroup $group, Carbon $enrolledAt, string $status = 'inscrito'): VolEnrollment
    {
        return VolEnrollment::factory()
            ->for($group, 'group')
            ->for(
                Beneficiario::factory()->state(['created_by' => $this->admin->uuid]),
                'beneficiario'
            )
            ->create([
                'status' => $status,
                'enrolled_at' => $enrolledAt->copy(),
                'unenrolled_at' => $status === 'baja' ? $enrolledAt->copy()->addDays(5) : null,
            ]);
    }
}
