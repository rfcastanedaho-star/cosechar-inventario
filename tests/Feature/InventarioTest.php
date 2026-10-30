<?php

namespace Tests\Feature;

use App\Livewire\InventarioLista;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Services\StockMovimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventarioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    private function operador(): User
    {
        return User::factory()->create(['rol' => 'operador_almacen']);
    }

    private function producto(array $datos = []): Producto
    {
        return Producto::factory()->create(['maneja_vencimiento' => false, 'stock_minimo' => 5, ...$datos]);
    }

    private function lote(Producto $producto, int $cantidad, array $datos = []): Lote
    {
        return Lote::factory()->for($producto, 'producto')->create(['almacen_id' => Almacen::first()->id, 'cantidad' => $cantidad, ...$datos]);
    }

    // ---------- Acceso y menú ----------

    public function test_pide_iniciar_sesion_y_esta_en_el_menu_en_lugar_de_ajustes_de_stock(): void
    {
        $this->get(route('inventario.index'))->assertRedirect(route('login'));

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('inventario.index'), false)
            ->assertSee('Inventario')
            ->assertDontSee('Ajustes de stock');
    }

    public function test_las_rutas_antiguas_llevan_a_lotes_y_al_kardex(): void
    {
        $this->actingAs($this->admin());

        $this->get('/movimientos/nuevo')->assertRedirect(route('lotes.index'));
        $this->get('/movimientos/historial')->assertRedirect(route('inventario.index', ['tab' => 'kardex']));
    }

    // ---------- Stock por producto ----------

    public function test_muestra_el_stock_de_cada_producto_sumando_sus_lotes(): void
    {
        $producto = $this->producto(['nombre' => 'Antracol 70 WP', 'codigo' => 'FUN-001', 'stock_minimo' => 10]);
        $this->lote($producto, 30);
        $this->lote($producto, 12);
        $this->lote($producto, 0);

        $texto = preg_replace('/\s+/u', ' ', strip_tags(Livewire::actingAs($this->admin())->test(InventarioLista::class)->html()));

        $this->assertStringContainsString('FUN-001', $texto);
        $this->assertStringContainsString('Antracol 70 WP', $texto);
        $this->assertMatchesRegularExpression('/Antracol 70 WP.*? 42 .*?2 lotes/u', $texto);
    }

    public function test_el_estado_es_sin_stock_stock_bajo_o_normal(): void
    {
        $this->lote($this->producto(['nombre' => 'Producto Vacio']), 0);
        $this->lote($this->producto(['nombre' => 'Producto Bajo', 'stock_minimo' => 10]), 4);
        $this->lote($this->producto(['nombre' => 'Producto Normal', 'stock_minimo' => 3]), 50);

        $componente = Livewire::actingAs($this->admin())->test(InventarioLista::class);

        $componente->assertSee('Sin stock')->assertSee('Stock bajo')->assertSee('Normal');

        $componente->set('estado', 'sin_stock')->assertSee('Producto Vacio')->assertDontSee('Producto Bajo')->assertDontSee('Producto Normal')
            ->set('estado', 'bajo')->assertSee('Producto Bajo')->assertDontSee('Producto Vacio')
            ->set('estado', 'normal')->assertSee('Producto Normal')->assertDontSee('Producto Bajo')
            ->set('estado', 'inventado')->assertSee('Producto Vacio')->assertSee('Producto Bajo')->assertSee('Producto Normal');
    }

    public function test_un_producto_sin_lotes_aparece_con_cero(): void
    {
        $this->producto(['nombre' => 'Producto Nuevo']);

        Livewire::actingAs($this->admin())->test(InventarioLista::class)->assertSee('Producto Nuevo')->assertSee('Sin stock');
    }

    public function test_los_productos_inactivos_no_aparecen(): void
    {
        $this->lote($this->producto(['nombre' => 'Producto Viejo', 'activo' => false]), 10);

        Livewire::actingAs($this->admin())->test(InventarioLista::class)->assertDontSee('Producto Viejo');
    }

    public function test_busca_por_nombre_codigo_o_marca(): void
    {
        $this->producto(['nombre' => 'Fungicida Cobre', 'codigo' => 'FUN-9', 'marca' => 'Bayer']);
        $this->producto(['nombre' => 'Urea Agricola', 'codigo' => 'FER-1', 'marca' => 'Yara']);

        foreach (['cobre', 'FUN-9', 'bayer'] as $termino) {
            Livewire::actingAs($this->admin())->test(InventarioLista::class)
                ->set('buscar', $termino)->assertSee('Fungicida Cobre')->assertDontSee('Urea Agricola');
        }
    }

    public function test_se_abre_un_producto_para_ver_sus_lotes_con_stock_y_ir_a_ajustarlos(): void
    {
        $producto = $this->producto(['nombre' => 'Antracol 70 WP']);
        $this->lote($producto, 30, ['numero_lote' => 'L-VIVO', 'fecha_vencimiento' => '2027-06-30']);
        $this->lote($producto, 0, ['numero_lote' => 'L-CERO']);

        Livewire::actingAs($this->admin())->test(InventarioLista::class)
            ->assertDontSee('L-VIVO')
            ->call('abrir', $producto->id)
            ->assertSet('abierto', $producto->id)
            ->assertSee('L-VIVO')
            ->assertSee('30/06/2027')
            ->assertDontSee('L-CERO')
            ->assertSeeHtml(route('lotes.index', ['buscar' => 'Antracol 70 WP']))
            ->call('abrir', $producto->id)
            ->assertSet('abierto', null);
    }

    // ---------- Tarjetas ----------

    public function test_las_tarjetas_resumen_productos_unidades_valor_y_stock_bajo(): void
    {
        $this->lote($this->producto(['stock_minimo' => 10]), 4, ['costo_unitario' => 5]);
        $this->lote($this->producto(['stock_minimo' => 3]), 50, ['costo_unitario' => 2]);

        $resumen = Livewire::actingAs($this->admin())->test(InventarioLista::class)->instance()->resumen;

        $this->assertSame(2, $resumen['productos']);
        $this->assertSame(54, $resumen['unidades']);
        $this->assertEqualsWithDelta(120.0, $resumen['valor'], 0.001);
        $this->assertSame(1, $resumen['bajo']);
    }

    public function test_el_valor_a_costo_solo_lo_ve_el_administrador(): void
    {
        $this->lote($this->producto(), 10, ['costo_unitario' => 5]);

        Livewire::actingAs($this->admin())->test(InventarioLista::class)->assertSee('Valor a costo')->assertSee('S/ 50.00');
        Livewire::actingAs($this->operador())->test(InventarioLista::class)->assertDontSee('Valor a costo')->assertDontSee('S/ 50.00');
    }

    // ---------- Kardex ----------

    public function test_las_pestanas_son_stock_y_kardex_y_una_invalida_cae_en_stock(): void
    {
        $this->producto(['nombre' => 'Producto Uno']);

        Livewire::actingAs($this->admin())->test(InventarioLista::class)
            ->assertSet('tab', 'stock')
            ->assertSee('Stock por producto')
            ->assertSee('Kardex')
            ->set('tab', 'kardex')
            ->assertSet('tab', 'kardex')
            ->set('tab', 'cualquiera')
            ->assertSet('tab', 'stock');
    }

    public function test_el_kardex_vive_dentro_del_inventario_y_recibe_los_ajustes_de_lotes(): void
    {
        $producto = $this->producto(['nombre' => 'Antracol 70 WP']);
        $lote = $this->lote($producto, 10, ['numero_lote' => 'L-KDX']);
        $usuario = $this->operador();

        app(StockMovimientoService::class)->ajustarLote($lote, 'salida', 2, 'perdida', $usuario);

        Livewire::withQueryParams(['tab' => 'kardex'])
            ->actingAs($usuario)
            ->test(InventarioLista::class)
            ->assertSet('tab', 'kardex')
            ->assertSee('Kardex')
            ->assertSee('Antracol 70 WP')
            ->assertSee('L-KDX')
            ->assertSee('Pérdida')
            ->assertSee($usuario->name);
    }

    public function test_el_kardex_ya_no_ofrece_registrar_movimientos_aparte(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(InventarioLista::class)->set('tab', 'kardex')->assertDontSee('Registrar movimiento');
    }
}
