<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Livewire\CompraLista;
use App\Livewire\ProductoLista;
use App\Livewire\VentaLista;
use App\Models\Almacen;
use App\Models\Compra;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HistorialesActualizadosTest extends TestCase
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

    /** Compra con una línea: 4 unidades de Fungicida Cobre a S/ 5.50. */
    private function compra(): Compra
    {
        $proveedor = Proveedor::factory()->create([
            'nombre' => 'Agro Andina SAC', 'telefono' => '987654321', 'correo' => 'ventas@agroandina.pe',
        ]);
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre']);
        $compra = Compra::factory()->create([
            'proveedor_id' => $proveedor->id, 'almacen_id' => Almacen::first()->id,
            'fecha' => '2026-10-05', 'numero_comprobante' => 'F001-000123', 'total' => 22,
        ]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $compra->almacen_id]);
        $compra->detalles()->create(['lote_id' => $lote->id, 'cantidad' => 4, 'costo_unitario' => 5.5]);

        return $compra;
    }

    // ---------- Historial de productos ----------

    public function test_el_historial_de_productos_muestra_precio_y_costo_al_administrador(): void
    {
        Producto::factory()->create(['nombre' => 'Hintecx', 'precio' => 95, 'precio_costo' => 70]);

        Livewire::actingAs($this->admin())
            ->test(ProductoLista::class)
            ->assertSee('Precio')
            ->assertSee('Costo')
            ->assertSee('S/ 95.00')
            ->assertSee('S/ 70.00');
    }

    public function test_el_operador_ve_el_precio_pero_no_el_costo(): void
    {
        Producto::factory()->create(['nombre' => 'Hintecx', 'precio' => 95, 'precio_costo' => 70]);

        Livewire::actingAs($this->operador())
            ->test(ProductoLista::class)
            ->assertSee('S/ 95.00')
            ->assertDontSee('S/ 70.00')
            ->assertDontSee('Costo');
    }

    public function test_un_producto_sin_costo_registrado_muestra_guion(): void
    {
        Producto::factory()->create(['precio' => 10, 'precio_costo' => null]);

        $texto = preg_replace('/\s+/u', ' ', strip_tags(Livewire::actingAs($this->admin())->test(ProductoLista::class)->html()));

        $this->assertStringContainsString('S/ 10.00 —', $texto);
    }

    // ---------- Historial de compras ----------

    public function test_el_historial_de_compras_usa_los_nombres_del_formulario(): void
    {
        $this->compra();

        Livewire::actingAs($this->admin())
            ->test(CompraLista::class)
            ->assertSee('Fecha de factura')
            ->assertSee('N.º de factura')
            ->assertSee('F001-000123')
            ->assertSee('05/10/2026')
            ->assertSee('Factura, proveedor o producto');
    }

    public function test_el_historial_de_compras_muestra_contacto_del_proveedor_y_cantidades(): void
    {
        $this->compra();

        Livewire::actingAs($this->admin())
            ->test(CompraLista::class)
            ->assertSee('987654321')
            ->assertSee('ventas@agroandina.pe')
            ->assertSee('Fungicida Cobre')
            ->assertSee('× 4');
    }

    public function test_el_historial_de_compras_busca_por_numero_de_factura(): void
    {
        $this->compra();

        Livewire::actingAs($this->admin())
            ->test(CompraLista::class)
            ->set('buscar', 'F001-000123')
            ->assertSee('Agro Andina SAC');
    }

    // ---------- Columna Almacén ----------

    public function test_con_un_solo_almacen_los_historiales_no_muestran_la_columna_almacen(): void
    {
        $this->compra();

        Livewire::actingAs($this->admin())->test(CompraLista::class)->assertDontSee('Almacén');
        Livewire::actingAs($this->admin())->test(VentaLista::class)->assertDontSee('Almacén');
    }

    public function test_con_varios_almacenes_la_columna_vuelve(): void
    {
        $this->compra();
        Almacen::factory()->create();

        Livewire::actingAs($this->admin())->test(CompraLista::class)->assertSee('Almacén');
        Livewire::actingAs($this->admin())->test(VentaLista::class)->assertSee('Almacén');
    }

    // ---------- Con 2 almacenes el formulario pregunta; al quedar uno, ya no ----------

    public function test_al_desactivar_el_almacen_sobrante_el_formulario_de_compras_ya_no_lo_pregunta(): void
    {
        $sobrante = Almacen::factory()->create(['nombre' => 'Almacen Secundario']);

        Livewire::actingAs($this->admin())->test(CompraForm::class)->assertSee('Almacén destino');

        $sobrante->update(['activo' => false]);

        Livewire::actingAs($this->admin())
            ->test(CompraForm::class)
            ->assertDontSee('Almacén destino')
            ->assertSet('almacen_id', Almacen::first()->id);
    }
}
