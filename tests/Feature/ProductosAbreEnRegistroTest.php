<?php

namespace Tests\Feature;

use App\Livewire\NotificacionesBell;
use App\Livewire\ProductoForm;
use App\Livewire\ProductoLista;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class ProductosAbreEnRegistroTest extends TestCase
{
    use RefreshDatabase;

    private function operador(): User
    {
        return User::factory()->create(['rol' => 'operador_almacen']);
    }

    public function test_el_menu_productos_abre_el_formulario_de_registro(): void
    {
        $this->actingAs($this->operador())
            ->get(route('productos.index'))
            ->assertOk()
            ->assertSeeLivewire(ProductoForm::class)
            ->assertSee('Registrar producto')
            ->assertSee('Ver historial de productos')
            ->assertSee(route('productos.historial'), false);
    }

    public function test_el_historial_lista_los_productos_y_ofrece_registrar_otro(): void
    {
        Producto::factory()->create(['nombre' => 'Urea agrícola']);

        $this->actingAs($this->operador())
            ->get(route('productos.historial'))
            ->assertOk()
            ->assertSeeLivewire(ProductoLista::class)
            ->assertSee('Historial de productos')
            ->assertSee('Urea agrícola')
            ->assertSee(route('productos.index'), false);
    }

    public function test_los_visitantes_son_enviados_al_login(): void
    {
        $this->get(route('productos.index'))->assertRedirect(route('login'));
        $this->get(route('productos.historial'))->assertRedirect(route('login'));
    }

    public function test_la_direccion_antigua_de_registro_lleva_al_formulario(): void
    {
        $this->assertFalse(Route::has('productos.nuevo'));

        $this->actingAs($this->operador())
            ->get('/productos/nuevo')
            ->assertRedirect(route('productos.index'));
    }

    public function test_al_editar_se_ofrece_volver_al_historial(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs(User::factory()->create(['rol' => 'administrador']))
            ->test(ProductoForm::class, ['producto' => $producto])
            ->assertSee('Editar producto')
            ->assertSee('Ver historial de productos');
    }

    public function test_la_alerta_de_stock_bajo_lleva_al_historial_de_productos(): void
    {
        Producto::factory()->create(['stock_minimo' => 10]);

        Livewire::actingAs($this->operador())
            ->test(NotificacionesBell::class)
            ->assertSeeHtml('href="'.route('productos.historial').'"')
            ->assertDontSeeHtml('href="'.route('productos.index').'"');
    }
}
