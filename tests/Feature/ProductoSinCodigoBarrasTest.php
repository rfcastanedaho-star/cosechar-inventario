<?php

namespace Tests\Feature;

use App\Livewire\ProductoForm;
use App\Models\Producto;
use App\Models\User;
use App\Repositories\ProductoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoSinCodigoBarrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_formulario_no_pide_codigo_de_barras_y_sigue_el_orden_codigo_nombre_marca_presentacion(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ProductoForm::class)
            ->assertDontSee('Código de barras')
            ->assertDontSee('lector')
            ->assertSeeInOrder(['Código', 'Nombre', 'Marca', 'Presentación', 'Categoría']);
    }

    public function test_la_tabla_de_productos_ya_no_tiene_la_columna(): void
    {
        $this->assertFalse(Schema::hasColumn('productos', 'codigo_barras'));
    }

    public function test_el_buscador_sigue_encontrando_por_nombre_codigo_y_marca(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'FUN-777', 'marca' => 'Bayer']);

        $repositorio = new ProductoRepository;

        foreach (['cobre', 'FUN-777', 'bayer'] as $termino) {
            $this->assertTrue($repositorio->buscar($termino)->contains('id', $producto->id), "No encontró por: {$termino}");
        }
    }

    public function test_un_producto_se_registra_sin_codigo_de_barras(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test(ProductoForm::class)
            ->set('nombre', 'Hintecx')
            ->set('categoria_id', $producto->categoria_id)
            ->set('unidad_medida', 'litro')
            ->set('precio', '95')
            ->set('precio_costo', '70')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('productos', ['nombre' => 'Hintecx']);
    }
}
