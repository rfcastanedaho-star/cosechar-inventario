<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\StockMinimoAlerta;
use App\Notifications\VencimientoProximoAlerta;
use App\Services\AlertaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('app:verificar-alertas')]
#[Description('Verifica stock mínimo y vencimientos próximos, y notifica por correo a los administradores.')]
class VerificarAlertas extends Command
{
    public function handle(AlertaService $alertas): int
    {
        $administradores = User::where('rol', 'administrador')->get();

        if ($administradores->isEmpty()) {
            $this->warn('No hay usuarios con rol administrador para notificar.');

            return self::SUCCESS;
        }

        $productos = $alertas->productosConStockMinimo();
        foreach ($productos as $producto) {
            Notification::send($administradores, new StockMinimoAlerta($producto));
        }

        $lotes = $alertas->lotesPorVencer();
        foreach ($lotes as $lote) {
            Notification::send($administradores, new VencimientoProximoAlerta($lote));
        }

        $this->info("Alertas de stock mínimo enviadas: {$productos->count()}");
        $this->info("Alertas de vencimiento próximo enviadas: {$lotes->count()}");

        return self::SUCCESS;
    }
}
