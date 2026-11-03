<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Models\Almacen;
use App\Models\Compra;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\CompraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un producto que no usa lotes se compra sin datos de lote y acumula en su lote automático «Sin lote».
 * Los productos con lotes piden su información de lote en la compra (ver CompraConLoteTest); el lote
 * automático «C{compra}-{línea}» solo se usa si la línea llega sin N.º de lote (por ejemplo, desde la API).
 */
class CompraSinLoteTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacen;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = Almacen::unicoActivo();
        $this->usuario = User::factory()->create();
    }

    private function comprar(Producto $producto, int $cantidad, float $costo): Compra
    {
        return app(CompraService::class)->registrar(
            ['proveedor_nombre' => 'Agro Sur', 'almacen_id' => $this->almacen->id, 'fecha' => '2026-10-20', 'numero_comprobante' => null],
            [['producto_id' => $producto->id, 'cantidad' => $cantidad, 'costo_unitario' => $costo]],
            $this->usuario,
        );
    }

    public function test_un_producto_sin_lotes_no_pide_informacion_de_lote(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        $componente = Livewire::actingAs($this->usuario)->test(CompraForm::class)
            ->call('elegirProductoLinea', 0, $producto->id);

        $texto = preg_replace('/\s+/u', ' ', strip_tags($componente->html()));

        $this->assertStringNotContainsString('Información del lote', $texto);
        $this->assertStringNotContainsString('N.º de lote', $texto);
    }

    public function test_un_producto_sin_lotes_se_compra_sin_datos_de_lote(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs($this->usuario)
            ->test(CompraForm::class)
            ->call('elegirProveedor', Proveedor::factory()->create(['nombre' => 'Agro Sur'])->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.cantidad', 12)
            ->set('lineas.0.costo_unitario', 4)
            ->call('revisar')
            ->assertHasNoErrors()
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(12, (int) Lote::where('producto_id', $producto->id)->sum('cantidad'));
    }

    public function test_un_producto_sin_lotes_acumula_siempre_en_un_unico_lote_automatico(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        $primera = $this->comprar($producto, 10, 2.00);
        $segunda = $this->comprar($producto, 30, 4.00);

        $lotes = Lote::where('producto_id', $producto->id)->get();

        $this->assertCount(1, $lotes);
        $this->assertSame(Lote::SIN_LOTE, $lotes->first()->numero_lote);
        $this->assertSame(40, $lotes->first()->cantidad);
        $this->assertSame($lotes->first()->id, $primera->detalles->first()->lote_id);
        $this->assertSame($lotes->first()->id, $segunda->detalles->first()->lote_id);
    }

    public function test_el_costo_del_lote_automatico_es_el_promedio_ponderado(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        $this->comprar($producto, 10, 2.00);
        $this->comprar($producto, 30, 4.00);

        // (10 × 2 + 30 × 4) / 40 = 3.50
        $this->assertSame('3.50', Lote::where('producto_id', $producto->id)->first()->costo_unitario);
    }

    public function test_una_linea_sin_numero_de_lote_recibe_un_lote_automatico_por_compra(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);

        $compra = $this->comprar($producto, 25, 3.00);

        $lote = $compra->detalles->first()->lote;

        $this->assertSame('C'.$compra->id.'-1', $lote->numero_lote);
        $this->assertNull($lote->fecha_vencimiento);
        $this->assertSame(25, $lote->cantidad);
        $this->assertSame('3.00', $lote->costo_unitario);
    }

    public function test_dos_compras_de_un_producto_con_lotes_no_se_mezclan(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => true]);

        $this->comprar($producto, 10, 2.00);
        $this->comprar($producto, 10, 2.00);

        $this->assertSame(2, Lote::where('producto_id', $producto->id)->count());
    }

    public function test_cada_linea_de_la_misma_compra_recibe_su_propio_numero(): void
    {
        $uno = Producto::factory()->create(['maneja_vencimiento' => true]);
        $dos = Producto::factory()->create(['maneja_vencimiento' => true]);

        $compra = app(CompraService::class)->registrar(
            ['proveedor_nombre' => 'Agro Sur', 'almacen_id' => $this->almacen->id, 'fecha' => '2026-10-20', 'numero_comprobante' => null],
            [
                ['producto_id' => $uno->id, 'cantidad' => 5, 'costo_unitario' => 1],
                ['producto_id' => $dos->id, 'cantidad' => 5, 'costo_unitario' => 1],
            ],
            $this->usuario,
        );

        $this->assertSame(
            ['C'.$compra->id.'-1', 'C'.$compra->id.'-2'],
            $compra->detalles->map(fn ($detalle) => $detalle->lote->numero_lote)->all(),
        );
    }

    public function test_el_lote_automatico_se_muestra_como_sin_lote(): void
    {
        $lote = Lote::factory()->create(['numero_lote' => Lote::SIN_LOTE]);

        $this->assertSame('Sin lote', $lote->etiqueta);
        $this->assertSame('L-9', Lote::factory()->make(['numero_lote' => 'L-9'])->etiqueta);
    }
}
