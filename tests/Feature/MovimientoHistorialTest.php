<?php

namespace Tests\Feature;

use App\Livewire\MovimientoLista;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MovimientoHistorialTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create(['name' => 'Rosa Responsable']);
    }

    private function lote(string $producto = 'Fungicida Cobre', string $numero = 'L-001', ?Almacen $almacen = null): Lote
    {
        return Lote::factory()
            ->for(Producto::factory()->create(['nombre' => $producto, 'codigo' => 'COD-'.$numero]), 'producto')
            ->create(['almacen_id' => ($almacen ?? Almacen::factory()->create(['nombre' => 'Almacén Central']))->id, 'numero_lote' => $numero]);
    }

    private function movimiento(Lote $lote, string $tipo = 'entrada', ?string $motivo = null, int $cantidad = 5, string $fecha = '2026-10-10 09:00:00'): StockMovimiento
    {
        return StockMovimiento::create([
            'lote_id' => $lote->id, 'tipo' => $tipo, 'cantidad' => $cantidad, 'fecha' => $fecha,
            'responsable_id' => $this->usuario->id, 'motivo' => $motivo,
        ]);
    }

    public function test_exige_haber_iniciado_sesion(): void
    {
        $this->get(route('inventario.index', ['tab' => 'kardex']))->assertRedirect(route('login'));
    }

    public function test_cualquier_usuario_autenticado_ve_el_kardex_dentro_del_inventario(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('inventario.index', ['tab' => 'kardex']))
            ->assertOk()
            ->assertSee('Kardex de movimientos');
    }

    public function test_lotes_enlaza_al_kardex(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('lotes.index'))
            ->assertSee(route('inventario.index', ['tab' => 'kardex']), false)
            ->assertSee('Ver kardex');
    }

    public function test_muestra_producto_lote_almacen_tipo_cantidad_y_responsable(): void
    {
        $this->movimiento($this->lote('Fungicida Cobre', 'L-2026-001'), 'salida', 'merma', 7);

        Livewire::actingAs($this->usuario)
            ->test(MovimientoLista::class)
            ->assertSee('Fungicida Cobre')
            ->assertSee('COD-L-2026-001')
            ->assertSee('L-2026-001')
            ->assertSee('Almacén Central')
            ->assertSee('Salida')
            ->assertSee('Merma')
            ->assertSee('7')
            ->assertSee('Rosa Responsable');
    }

    /** @return array<string, array{?string, string, string}> */
    public static function etiquetasDeMotivo(): array
    {
        return [
            'compra' => ['compra', 'entrada', 'Compra'],
            'entrada antigua sin motivo' => [null, 'entrada', 'Compra'],
            'venta' => ['venta', 'salida', 'Venta'],
            'merma' => ['merma', 'salida', 'Merma'],
            'dañado' => ['producto_danado', 'salida', 'Producto dañado'],
            'ajuste' => ['ajuste_inventario', 'entrada', 'Ajuste por conteo'],
            'transferencia' => ['transferencia', 'salida', 'Transferencia'],
            'anulacion' => ['anulacion', 'entrada', 'Anulación'],
            'saldo inicial' => ['saldo_inicial', 'entrada', 'Saldo inicial'],
        ];
    }

    #[DataProvider('etiquetasDeMotivo')]
    public function test_cada_motivo_se_muestra_con_su_nombre_legible(?string $motivo, string $tipo, string $etiqueta): void
    {
        $this->assertSame($etiqueta, StockMovimiento::make(['motivo' => $motivo, 'tipo' => $tipo])->etiquetaMotivo);
    }

    public function test_una_salida_sin_motivo_se_muestra_como_guion(): void
    {
        $this->assertSame('—', StockMovimiento::make(['motivo' => null, 'tipo' => 'salida'])->etiquetaMotivo);
    }

    public function test_indica_la_venta_de_origen_si_existe(): void
    {
        $venta = Venta::factory()->create();
        $mov = $this->movimiento($this->lote(), 'salida', 'venta');
        $mov->update(['venta_id' => $venta->id]);

        Livewire::actingAs($this->usuario)
            ->test(MovimientoLista::class)
            ->assertSee('Venta #'.$venta->id);
    }

    public function test_muestra_primero_lo_mas_reciente_y_pagina_de_a_20(): void
    {
        $lote = $this->lote();
        foreach (range(1, 25) as $n) {
            $this->movimiento($lote, cantidad: $n, fecha: now()->subMinutes(100 - $n)->toDateTimeString());
        }

        $componente = Livewire::actingAs($this->usuario)->test(MovimientoLista::class);

        $this->assertCount(20, $componente->instance()->movimientos->items());
        $this->assertSame(25, $componente->instance()->movimientos->items()[0]->cantidad);
        $this->assertSame(25, $componente->instance()->movimientos->total());
    }

    public function test_filtra_por_texto_tipo_almacen_motivo_y_fechas(): void
    {
        $central = Almacen::factory()->create(['nombre' => 'Central']);
        $sur = Almacen::factory()->create(['nombre' => 'Sur']);
        $this->movimiento($this->lote('Fungicida Cobre', 'L-1', $central), 'entrada', 'compra', fecha: '2026-10-02 09:00:00');
        $this->movimiento($this->lote('Herbicida Total', 'L-2', $sur), 'salida', 'merma', fecha: '2026-10-08 09:00:00');

        $componente = Livewire::actingAs($this->usuario)->test(MovimientoLista::class);

        $componente->set('buscar', 'herbi')->assertSee('Herbicida Total')->assertDontSee('Fungicida Cobre');
        $componente->set('buscar', '')->set('tipo', 'entrada')->assertSee('Fungicida Cobre')->assertDontSee('Herbicida Total');
        $componente->set('tipo', '')->set('almacen_id', $sur->id)->assertSee('Herbicida Total')->assertDontSee('Fungicida Cobre');
        $componente->set('almacen_id', null)->set('motivo', 'merma')->assertSee('Herbicida Total')->assertDontSee('Fungicida Cobre');
        $componente->set('motivo', '')->set('desde', '2026-10-05')->assertSee('Herbicida Total')->assertDontSee('Fungicida Cobre');
        $componente->set('desde', '')->set('hasta', '2026-10-05')->assertSee('Fungicida Cobre')->assertDontSee('Herbicida Total');
    }

    public function test_limpiar_quita_todos_los_filtros(): void
    {
        $this->movimiento($this->lote());

        Livewire::actingAs($this->usuario)
            ->test(MovimientoLista::class)
            ->set('buscar', 'x')
            ->set('tipo', 'salida')
            ->set('desde', '2026-01-01')
            ->call('limpiarFiltros')
            ->assertSet('buscar', '')
            ->assertSet('tipo', '')
            ->assertSet('desde', '')
            ->assertSet('hasta', '')
            ->assertSet('almacen_id', null)
            ->assertSet('motivo', '');
    }

    public function test_cambiar_un_filtro_vuelve_a_la_primera_pagina(): void
    {
        $lote = $this->lote();
        foreach (range(1, 25) as $n) {
            $this->movimiento($lote);
        }

        Livewire::actingAs($this->usuario)
            ->test(MovimientoLista::class)
            ->call('gotoPage', 2)
            ->set('tipo', 'entrada')
            ->assertSet('paginators.page', 1);
    }

    public function test_avisa_cuando_no_hay_movimientos(): void
    {
        Livewire::actingAs($this->usuario)
            ->test(MovimientoLista::class)
            ->assertSee('No hay movimientos');

        $this->movimiento($this->lote());

        Livewire::actingAs($this->usuario)
            ->test(MovimientoLista::class)
            ->set('buscar', 'zzz')
            ->assertSee('No se encontraron movimientos con esos filtros');
    }

    public function test_rechaza_valores_de_filtro_fuera_de_lo_permitido(): void
    {
        $this->movimiento($this->lote());

        Livewire::actingAs($this->usuario)
            ->test(MovimientoLista::class)
            ->set('tipo', 'cualquiera')
            ->set('motivo', 'inventado')
            ->set('desde', 'no-es-fecha')
            ->assertOk();
    }
}
