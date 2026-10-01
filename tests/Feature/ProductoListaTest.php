<?php

namespace Tests\Feature;

use App\Livewire\ProductoLista;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_administrador_puede_modificar_el_stock_minimo(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $producto = Producto::factory()->create(['stock_minimo' => 5]);

        Livewire::actingAs($admin)
            ->test(ProductoLista::class)
            ->set("stockMinimoEdit.{$producto->id}", 15)
            ->call('guardarStockMinimo', $producto->id)
            ->assertHasNoErrors();

        $this->assertSame(15, $producto->fresh()->stock_minimo);
    }

    public function test_el_operador_no_puede_modificar_el_stock_minimo(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);
        $producto = Producto::factory()->create(['stock_minimo' => 5]);

        Livewire::actingAs($operador)
            ->test(ProductoLista::class)
            ->set("stockMinimoEdit.{$producto->id}", 99)
            ->call('guardarStockMinimo', $producto->id)
            ->assertForbidden();

        $this->assertSame(5, $producto->fresh()->stock_minimo);
    }

    public function test_no_permite_un_stock_minimo_negativo(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $producto = Producto::factory()->create(['stock_minimo' => 5]);

        Livewire::actingAs($admin)
            ->test(ProductoLista::class)
            ->set("stockMinimoEdit.{$producto->id}", -1)
            ->call('guardarStockMinimo', $producto->id)
            ->assertHasErrors(["stockMinimoEdit.{$producto->id}"]);

        $this->assertSame(5, $producto->fresh()->stock_minimo);
    }

    public function test_el_operador_puede_ver_la_lista_de_productos(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);

        $this->actingAs($operador)->get(route('productos.index'))->assertOk();
    }

    public function test_el_buscador_filtra_por_nombre_o_codigo(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);

        Producto::factory()->create(['nombre' => 'Urea agrícola', 'codigo' => 'FERT-001']);
        Producto::factory()->create(['nombre' => 'Semilla de maíz', 'codigo' => 'SEM-002']);

        $porNombre = Livewire::actingAs($operador)
            ->test(ProductoLista::class)
            ->set('buscar', 'urea')
            ->instance()
            ->productos;

        $this->assertCount(1, $porNombre);
        $this->assertSame('Urea agrícola', $porNombre->first()->nombre);

        $porCodigo = Livewire::actingAs($operador)
            ->test(ProductoLista::class)
            ->set('buscar', 'SEM-002')
            ->instance()
            ->productos;

        $this->assertCount(1, $porCodigo);
        $this->assertSame('Semilla de maíz', $porCodigo->first()->nombre);
    }

    public function test_sin_busqueda_se_listan_todos_los_productos(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);

        Producto::factory()->count(3)->create();

        $todos = Livewire::actingAs($operador)
            ->test(ProductoLista::class)
            ->instance()
            ->productos;

        $this->assertCount(3, $todos);
    }
}
