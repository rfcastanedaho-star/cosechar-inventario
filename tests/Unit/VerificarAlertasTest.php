<?php

namespace Tests\Unit;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Notifications\StockMinimoAlerta;
use App\Notifications\VencimientoProximoAlerta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VerificarAlertasTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifica_a_los_administradores_por_stock_minimo(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['rol' => 'administrador']);
        User::factory()->create(['rol' => 'operador_almacen']);

        $producto = Producto::factory()->create(['stock_minimo' => 20]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 5]);

        $this->artisan('app:verificar-alertas')->assertExitCode(0);

        Notification::assertSentTo($admin, StockMinimoAlerta::class);
    }

    public function test_no_notifica_al_operador_de_almacen(): void
    {
        Notification::fake();

        $operador = User::factory()->create(['rol' => 'operador_almacen']);

        $producto = Producto::factory()->create(['stock_minimo' => 20]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 5]);

        $this->artisan('app:verificar-alertas');

        Notification::assertNotSentTo($operador, StockMinimoAlerta::class);
    }

    public function test_notifica_a_los_administradores_por_vencimiento_proximo(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['rol' => 'administrador']);
        $producto = Producto::factory()->create(['maneja_vencimiento' => true, 'stock_minimo' => 0]);
        Lote::factory()->for($producto, 'producto')->create([
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
            'cantidad' => 10,
        ]);

        $this->artisan('app:verificar-alertas');

        Notification::assertSentTo($admin, VencimientoProximoAlerta::class);
    }

    public function test_no_envia_nada_cuando_no_hay_alertas(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['rol' => 'administrador']);
        $producto = Producto::factory()->create(['stock_minimo' => 5]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 50]);

        $this->artisan('app:verificar-alertas');

        Notification::assertNothingSentTo($admin);
    }
}
