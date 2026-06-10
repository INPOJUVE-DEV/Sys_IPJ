<?php

namespace App\Console\Commands;

use App\Models\Beneficiario;
use App\Models\Oficina;
use App\Models\User;
use App\Services\TarjetaService;
use Illuminate\Console\Command;

class InventoryBackfillTarjetas extends Command
{
    protected $signature = 'inventario:backfill-tarjetas';

    protected $description = 'Crea tarjetas historicas consumidas para beneficiarios existentes con folio_tarjeta';

    public function handle(TarjetaService $tarjetaService): int
    {
        $central = Oficina::firstOrCreate(
            ['nombre' => 'Central'],
            ['tipo' => Oficina::TIPO_CENTRAL, 'activo' => true]
        );

        $actor = User::role('admin')->first() ?: User::query()->first();
        if (! $actor) {
            $this->error('No existe un usuario para registrar la auditoria del backfill.');

            return self::FAILURE;
        }

        $count = 0;
        Beneficiario::query()
            ->whereNotNull('folio_tarjeta')
            ->where(function ($query) {
                $query->whereNull('tarjeta_id')->orWhere('tarjeta_id', '');
            })
            ->chunkById(100, function ($beneficiarios) use (&$count, $tarjetaService, $central, $actor) {
                foreach ($beneficiarios as $beneficiario) {
                    $tarjeta = $tarjetaService->backfillConsumedCard($beneficiario, $central, $actor);
                    $beneficiario->forceFill([
                        'tarjeta_id' => $tarjeta->id,
                        'folio_tarjeta' => $tarjeta->folio,
                    ])->save();
                    $count++;
                }
            }, 'id');

        $this->info("Tarjetas historicas procesadas: {$count}");

        return self::SUCCESS;
    }
}
