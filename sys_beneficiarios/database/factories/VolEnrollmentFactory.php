<?php

namespace Database\Factories;

use App\Models\Beneficiario;
use App\Models\User;
use App\Models\VolEnrollment;
use App\Models\VolGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\VolEnrollment>
 */
class VolEnrollmentFactory extends Factory
{
    protected $model = VolEnrollment::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['inscrito', 'baja']);
        $enrolledAt = fake()->dateTimeBetween('-2 months', 'now');
        $unenrolledAt = $status === 'baja' ? fake()->dateTimeBetween($enrolledAt, 'now') : null;

        return [
            'group_id' => VolGroup::factory(),
            'beneficiario_id' => Beneficiario::factory(),
            'status' => $status,
            'enrolled_at' => $enrolledAt->format('Y-m-d H:i:s'),
            'unenrolled_at' => $unenrolledAt ? $unenrolledAt->format('Y-m-d H:i:s') : null,
            'reason' => $status === 'baja' ? fake()->sentence() : null,
            'created_by' => User::factory(),
        ];
    }
}
