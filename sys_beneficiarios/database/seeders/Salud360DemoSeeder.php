<?php

namespace Database\Seeders;

use App\Models\Beneficiario;
use App\Models\Municipio;
use App\Models\Salud360Assignment;
use App\Models\Salud360Session;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Salud360DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Roles base
        if (class_exists(Salud360RolesSeeder::class)) {
            $this->call(Salud360RolesSeeder::class);
        }

        // Usuarios
        $admin = User::firstOrCreate(
            ['email' => 'admin+s360@example.com'],
            [
                'name' => 'Admin S360',
                'password' => 'Password123',
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);

        $encBien = User::firstOrCreate(
            ['email' => 'bienestar@example.com'],
            [
                'name' => 'Encargado Bienestar',
                'password' => 'Password123',
                'email_verified_at' => now(),
            ]
        );
        $encBien->syncRoles(['encargado_bienestar']);

        $enc360 = [];
        foreach ([1, 2] as $i) {
            $u = User::firstOrCreate(
                ['email' => "enc360{$i}@example.com"],
                [
                    'name' => "Enc360 {$i}",
                    'password' => 'Password123',
                    'email_verified_at' => now(),
                ]
            );
            $u->syncRoles(['encargado_360']);
            $enc360[] = $u;
        }

        $psicologos = [];
        foreach (range(1, 5) as $i) {
            $u = User::firstOrCreate(
                ['email' => "psico{$i}@example.com"],
                [
                    'name' => "Psicólogo {$i}",
                    'password' => 'Password123',
                    'email_verified_at' => now(),
                ]
            );
            $u->syncRoles(['psicologo']);
            $psicologos[] = $u;
        }

        // Municipios demo
        $munNames = ['Norte', 'Sur', 'Este', 'Oeste', 'Centro'];
        $municipios = [];
        foreach ($munNames as $idx => $name) {
            $m = Municipio::firstOrCreate(
                ['clave' => $idx + 1],
                ['nombre' => $name]
            );
            $municipios[] = $m;
        }

        // Beneficiarios (20)
        $beneficiarios = [];
        foreach (range(1, 20) as $i) {
            $m = Arr::random($municipios);
            $creator = Arr::random(array_merge([$admin, $encBien], $enc360));
            $sexo = Arr::random(['M','F']);
            $dob = Carbon::now()->subYears(rand(18, 75))->subDays(rand(0, 365));
            $b = Beneficiario::firstOrCreate(
                ['curp' => Str::upper(Str::random(18))],
                [
                    'id' => (string) Str::uuid(),
                    'folio_tarjeta' => Str::upper(Str::random(10)),
                    'nombre' => fake()->firstName($sexo === 'M' ? 'male' : 'female'),
                    'apellido_paterno' => fake()->lastName(),
                    'apellido_materno' => fake()->lastName(),
                    'fecha_nacimiento' => $dob->toDateString(),
                    'edad' => $dob->age,
                    'sexo' => $sexo,
                    'discapacidad' => false,
                    'id_ine' => null,
                    'telefono' => '55'.str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'municipio_id' => $m->id,
                    'seccional' => str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'distrito_local' => str_pad((string)rand(1, 20), 2, '0', STR_PAD_LEFT),
                    'distrito_federal' => str_pad((string)rand(1, 20), 2, '0', STR_PAD_LEFT),
                    'created_by' => $creator->uuid,
                ]
            );
            $beneficiarios[] = $b;
        }

        // Assignments (uno por beneficiario)
        $assignments = [];
        foreach ($beneficiarios as $b) {
            $ps = Arr::random($psicologos);
            $asBy = Arr::random($enc360);
            $assignedAt = Carbon::now()->subDays(rand(15, 60))->subHours(rand(0, 72));
            $a = Salud360Assignment::updateOrCreate(
                ['beneficiario_id' => $b->id],
                [
                    'psicologo_id' => $ps->id,
                    'assigned_by' => $asBy->id,
                    'assigned_at' => $assignedAt,
                    'active' => true,
                ]
            );
            $assignments[] = $a;
        }

        // Sessions: objetivo total ~120
        $totalSessions = 120;
        $left = $totalSessions;
        $perBen = [];
        foreach ($beneficiarios as $idx => $b) {
            $remaining = count($beneficiarios) - $idx;
            // al menos 1 sesión por beneficiario, máximo 8
            $maxForThis = min(8, $left - ($remaining - 1));
            $minForThis = 1;
            $n = rand($minForThis, max($minForThis, $maxForThis));
            $perBen[$b->id] = $n;
            $left -= $n;
        }
        // si sobró o faltó, ajusta distribuyendo
        while ($left > 0) {
            $b = Arr::random($beneficiarios);
            $perBen[$b->id]++;
            $left--;
        }

        foreach ($beneficiarios as $b) {
            $ps = Arr::first($assignments, fn($a)=>$a->beneficiario_id === $b->id);
            if (!$ps) { continue; }
            $psId = $ps->psicologo_id;
            $count = $perBen[$b->id] ?? 1;
            $startDate = Carbon::now()->subDays(rand(20, 60));
            for ($i = 1; $i <= $count; $i++) {
                $date = (clone $startDate)->addDays(rand(3, 10));
                $startDate = $date;

                $isFirst = $i === 1;
                $payload = [
                    'beneficiario_id' => $b->id,
                    'psicologo_id' => $psId,
                    'session_date' => $date->toDateString(),
                    'session_number' => $i,
                    'is_first' => $isFirst,
                    'created_by' => $psId,
                ];
                if ($isFirst) {
                    $payload['motivo_consulta'] = fake()->sentence(8);
                    $payload['riesgo_suicida'] = (bool)rand(0,1);
                    $payload['uso_sustancias'] = (bool)rand(0,1);
                } else {
                    // cada 2 sesiones agenda próxima cita
                    if ($i % 2 === 0) {
                        $payload['next_session_date'] = (clone $date)->addDays(rand(3, 14))->toDateString();
                        $payload['next_objective'] = fake()->sentence(6);
                    }
                    if (rand(0,1)) {
                        $payload['notes'] = fake()->sentence(12);
                    }
                }

                Salud360Session::create($payload);
            }
        }

        $this->command?->info('Salud360 demo data seeded: users, beneficiaries, assignments, sessions.');
    }
}

