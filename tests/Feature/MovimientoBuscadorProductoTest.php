<?php

namespace Tests\Feature;

use App\Livewire\MovimientoForm;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class MovimientoBuscadorProductoTest extends TestCase
{
    use RefreshDatabase;

    private function formulario(): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(MovimientoForm::class);
    }

    public function test_al_escribir_las_iniciales_sugiere_los_productos_que_coinciden(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'P-001']);
        Producto::factory()->create(['nombre' => 'Fertilizante Urea', 'codigo' => 'P-002']);

        $this->formulario()
            ->set('buscarProducto', 'fu')
            ->assertSee('Fungicida Cobre')
            ->assertDontSee('Fertilizante Urea');
    }

    public function test_tambien_se_busca_por_codigo(): void
    {
        Producto::factory()->create(['nombre' => 'Insecticida Alfa', 'codigo' => 'INS-2201']);
        Producto::factory()->create(['nombre' => 'Herbicida Beta', 'codigo' => 'HER-0001']);

        $this->formulario()
            ->set('buscarProducto', 'INS-22')
            ->assertSee('Insecticida Alfa')
            ->assertDontSee('Herbicida Beta');
    }

    public function test_sin_texto_no_muestra_sugerencias(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre']);

        $this->formulario()
            ->assertDontSee('Fungicida Cobre')
            ->assertSee('Escriba el nombre o el código');
    }

    public function test_avisa_cuando_no_hay_coincidencias(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre']);

        $this->formulario()
            ->set('buscarProducto', 'zzz')
            ->assertSee('No se encontró ningún producto');
    }

    public function test_muestra_como_maximo_ocho_sugerencias(): void
    {
        Producto::factory()->count(12)->sequence(fn ($s) => ['nombre' => 'Abono '.$s->index])->create();

        $componente = $this->formulario()->set('buscarProducto', 'abono');

        $this->assertCount(8, $componente->instance()->productosSugeridos);
    }

    public function test_elegir_una_sugerencia_selecciona_el_producto_y_limpia_la_busqueda(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'P-001']);

        $this->formulario()
            ->set('buscarProducto', 'fu')
            ->call('elegirProducto', $producto->id)
            ->assertSet('producto_id', $producto->id)
            ->assertSet('buscarProducto', '')
            ->assertSee('P-001')
            ->assertSee('Fungicida Cobre')
            ->assertSee('Cambiar');
    }

    public function test_enter_elige_la_primera_sugerencia(): void
    {
        Producto::factory()->create(['nombre' => 'Mancozeb Fungicida', 'codigo' => 'P-010']);
        $primero = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'P-011']);

        $this->formulario()
            ->set('buscarProducto', 'fung')
            ->call('elegirPrimero')
            ->assertSet('producto_id', $primero->id);
    }

    public function test_enter_sin_coincidencias_no_elige_nada(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre']);

        $this->formulario()
            ->set('buscarProducto', 'zzz')
            ->call('elegirPrimero')
            ->assertSet('producto_id', null);
    }

    public function test_cambiar_de_producto_limpia_producto_y_lote(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 10]);

        $this->formulario()
            ->call('elegirProducto', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('lote_id', $lote->id)
            ->call('limpiarProducto')
            ->assertSet('producto_id', null)
            ->assertSet('lote_id', null)
            ->assertSee('Escriba el nombre o el código');
    }

    public function test_elegir_otro_producto_limpia_el_lote_del_anterior(): void
    {
        $almacen = Almacen::factory()->create();
        $uno = Producto::factory()->create(['maneja_vencimiento' => false]);
        $otro = Producto::factory()->create(['maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($uno, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 10]);

        $this->formulario()
            ->call('elegirProducto', $uno->id)
            ->set('almacen_id', $almacen->id)
            ->set('lote_id', $lote->id)
            ->call('elegirProducto', $otro->id)
            ->assertSet('lote_id', null);
    }

    public function test_no_se_puede_elegir_un_producto_que_no_existe(): void
    {
        $this->formulario()
            ->call('elegirProducto', 99999)
            ->assertSet('producto_id', null);
    }

    public function test_el_buscador_funciona_tambien_en_salida_y_transferencia(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre']);

        foreach (['salida', 'transferencia'] as $tipo) {
            $this->formulario()
                ->set('tipo', $tipo)
                ->set('buscarProducto', 'fu')
                ->assertSee('Fungicida Cobre');
        }
    }

    public function test_el_formulario_completo_se_puede_guardar_eligiendo_el_producto_con_el_buscador(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'maneja_vencimiento' => false]);
        $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 10]);

        $this->formulario()
            ->set('buscarProducto', 'fung')
            ->call('elegirPrimero')
            ->set('almacen_id', $almacen->id)
            ->set('lote_id', $lote->id)
            ->set('cantidad', 6)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(16, $lote->fresh()->cantidad);
    }
}
