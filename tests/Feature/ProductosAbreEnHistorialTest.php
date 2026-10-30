<?php

namespace Tests\Feature;

use App\Livewire\NotificacionesBell;
use App\Livewire\ProductoForm;
use App\Livewire\ProductoLista;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductosAbreEnHistorialTest extends TestCase
{
    use RefreshDatabase;

    private function operador(): User
    {
        return User::factory()->create(['rol' => 'operador_almacen']);
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    public function test_el_menu_productos_abre_el_historial_con_el_boton_de_registrar(): void
    {
        Producto::factory()->create(['nombre' => 'Urea agrícola']);

        $this->actingAs($this->operador())
            ->get(route('productos.index'))
            ->assertOk()
            ->assertSeeLivewire(ProductoLista::class)
            ->assertSee('Historial de productos')
            ->assertSee('Urea agrícola')
            ->assertSee('Registrar producto')
            ->assertSee(route('productos.nuevo'), false);
    }

    public function test_el_formulario_de_registro_tiene_su_direccion_y_vuelve_al_historial(): void
    {
        $this->actingAs($this->operador())
            ->get(route('productos.nuevo'))
            ->assertOk()
            ->assertSeeLivewire(ProductoForm::class)
            ->assertSee('Ver historial de productos')
            ->assertSee(route('productos.index'), false);
    }

    public function test_la_direccion_intermedia_del_historial_redirige_a_productos(): void
    {
        $this->actingAs($this->operador())
            ->get('/productos/historial')
            ->assertRedirect(route('productos.index'));
    }

    public function test_los_visitantes_son_enviados_al_login(): void
    {
        $this->get(route('productos.index'))->assertRedirect(route('login'));
        $this->get(route('productos.nuevo'))->assertRedirect(route('login'));
    }

    public function test_la_alerta_de_stock_bajo_lleva_al_historial_de_productos(): void
    {
        Producto::factory()->create(['stock_minimo' => 10]);

        Livewire::actingAs($this->operador())
            ->test(NotificacionesBell::class)
            ->assertSeeHtml('href="'.route('productos.index').'"')
            ->assertDontSeeHtml('href="'.route('productos.nuevo').'"');
    }

    // ---------- Categorías ----------

    public function test_categorias_ya_no_esta_en_el_menu(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('categorias.index'), false);
    }

    public function test_el_administrador_ve_el_boton_categorias_junto_al_filtro(): void
    {
        Categoria::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ProductoLista::class)
            ->assertSee('Todas las categorías')
            ->assertSeeHtml('href="'.route('categorias.index').'"')
            ->assertSeeInOrder(['Todas las categorías', 'Categorías', 'Mostrar inactivos']);
    }

    public function test_el_operador_no_ve_el_boton_categorias(): void
    {
        Livewire::actingAs($this->operador())
            ->test(ProductoLista::class)
            ->assertDontSeeHtml('href="'.route('categorias.index').'"');
    }

    public function test_la_pantalla_de_categorias_permite_volver_a_productos(): void
    {
        $this->actingAs($this->admin())
            ->get(route('categorias.index'))
            ->assertOk()
            ->assertSee('Volver a productos')
            ->assertSee(route('productos.index'), false);
    }
}
