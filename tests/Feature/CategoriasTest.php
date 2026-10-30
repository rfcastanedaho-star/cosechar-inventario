<?php

namespace Tests\Feature;

use App\Livewire\CategoriaForm;
use App\Livewire\CategoriaLista;
use App\Livewire\ProductoForm;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Support\AuditoriaEtiquetas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriasTest extends TestCase
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

    // ---------- Acceso y menú ----------

    public function test_solo_el_administrador_gestiona_las_categorias(): void
    {
        $categoria = Categoria::factory()->create();

        foreach ([route('categorias.index'), route('categorias.nueva'), route('categorias.editar', $categoria)] as $url) {
            $this->actingAs($this->operador())->get($url)->assertForbidden();
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    public function test_los_visitantes_son_enviados_al_login(): void
    {
        $this->get(route('categorias.index'))->assertRedirect(route('login'));
    }

    public function test_un_operador_no_puede_abrir_los_componentes(): void
    {
        Livewire::actingAs($this->operador())->test(CategoriaLista::class)->assertForbidden();
        Livewire::actingAs($this->operador())->test(CategoriaForm::class)->assertForbidden();
    }

    // ---------- Lista ----------

    public function test_la_lista_muestra_grupos_subcategorias_y_cuantos_productos_tiene_cada_una(): void
    {
        $grupo = Categoria::factory()->create(['nombre' => 'Agroquímicos']);
        $fungicida = Categoria::factory()->create(['nombre' => 'Fungicida', 'categoria_padre_id' => $grupo->id]);
        Categoria::factory()->create(['nombre' => 'Herbicida', 'categoria_padre_id' => $grupo->id]);
        Producto::factory()->count(3)->create(['categoria_id' => $fungicida->id]);
        $herramientas = Categoria::factory()->create(['nombre' => 'Herramientas']);
        Producto::factory()->count(2)->create(['categoria_id' => $herramientas->id]);

        Livewire::actingAs($this->admin())
            ->test(CategoriaLista::class)
            ->assertSee('Agroquímicos')
            ->assertSee('Fungicida')
            ->assertSee('Herbicida')
            ->assertSee('Herramientas')
            ->assertSee('3 productos')
            ->assertSee('2 productos')
            ->assertSee('0 productos');
    }

    public function test_la_lista_ordena_alfabeticamente_los_grupos_y_sus_subcategorias(): void
    {
        $zeta = Categoria::factory()->create(['nombre' => 'Zeta']);
        $alfa = Categoria::factory()->create(['nombre' => 'Alfa']);
        Categoria::factory()->create(['nombre' => 'Sub B', 'categoria_padre_id' => $alfa->id]);
        Categoria::factory()->create(['nombre' => 'Sub A', 'categoria_padre_id' => $alfa->id]);

        $grupos = Livewire::actingAs($this->admin())->test(CategoriaLista::class)->instance()->grupos;

        $this->assertSame(['Alfa', 'Zeta'], $grupos->pluck('nombre')->all());
        $this->assertSame(['Sub A', 'Sub B'], $grupos->first()->subcategorias->pluck('nombre')->all());
        $this->assertNotNull($zeta);
    }

    public function test_la_lista_elimina_una_categoria_vacia(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Obsoleta']);

        Livewire::actingAs($this->admin())
            ->test(CategoriaLista::class)
            ->call('eliminar', $categoria->id)
            ->assertSet('error', null)
            ->assertDontSee('Obsoleta');

        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
    }

    public function test_la_lista_explica_por_que_no_se_puede_eliminar(): void
    {
        $conProductos = Categoria::factory()->create();
        Producto::factory()->create(['categoria_id' => $conProductos->id]);
        $grupo = Categoria::factory()->create();
        Categoria::factory()->create(['categoria_padre_id' => $grupo->id]);

        $lista = Livewire::actingAs($this->admin())->test(CategoriaLista::class);

        $lista->call('eliminar', $conProductos->id)->assertSet('error', 'No se puede eliminar: la categoría tiene productos asignados.');
        $lista->call('eliminar', $grupo->id)->assertSet('error', 'No se puede eliminar: el grupo tiene subcategorías.');

        $this->assertDatabaseHas('categorias', ['id' => $conProductos->id]);
        $this->assertDatabaseHas('categorias', ['id' => $grupo->id]);
    }

    // ---------- Formulario ----------

    public function test_crea_un_grupo_principal(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CategoriaForm::class)
            ->set('nombre', 'Equipos de campo')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('categorias.index'));

        $this->assertDatabaseHas('categorias', ['nombre' => 'Equipos de campo', 'categoria_padre_id' => null]);
    }

    public function test_crea_una_subcategoria_eligiendo_su_grupo(): void
    {
        $grupo = Categoria::factory()->create(['nombre' => 'Semillas']);

        Livewire::actingAs($this->admin())
            ->test(CategoriaForm::class)
            ->set('nombre', 'Hortalizas')
            ->set('categoria_padre_id', $grupo->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categorias', ['nombre' => 'Hortalizas', 'categoria_padre_id' => $grupo->id]);
    }

    public function test_la_pantalla_puede_abrirse_con_el_grupo_ya_elegido(): void
    {
        $grupo = Categoria::factory()->create(['nombre' => 'Semillas']);

        Livewire::actingAs($this->admin())
            ->withQueryParams(['grupo' => $grupo->id])
            ->test(CategoriaForm::class)
            ->assertSet('categoria_padre_id', $grupo->id);
    }

    public function test_valida_nombre_y_explica_los_rechazos_del_servicio(): void
    {
        $grupo = Categoria::factory()->create(['nombre' => 'Semillas']);
        Categoria::factory()->create(['nombre' => 'Hortalizas', 'categoria_padre_id' => $grupo->id]);
        $conProductos = Categoria::factory()->create();
        Producto::factory()->create(['categoria_id' => $conProductos->id]);

        $formulario = fn () => Livewire::actingAs($this->admin())->test(CategoriaForm::class);

        $formulario()->set('nombre', '')->call('guardar')->assertHasErrors(['nombre' => 'required']);
        $formulario()->set('nombre', str_repeat('a', 101))->call('guardar')->assertHasErrors(['nombre' => 'max']);
        $formulario()->set('nombre', 'hortalizas')->set('categoria_padre_id', $grupo->id)->call('guardar')->assertHasErrors(['nombre']);
        $formulario()->set('nombre', 'Nueva')->set('categoria_padre_id', $conProductos->id)->call('guardar')->assertHasErrors(['categoria_padre_id']);
    }

    public function test_el_grupo_elegido_debe_existir_y_ser_principal(): void
    {
        $grupo = Categoria::factory()->create();
        $sub = Categoria::factory()->create(['categoria_padre_id' => $grupo->id]);

        $formulario = fn () => Livewire::actingAs($this->admin())->test(CategoriaForm::class);

        $formulario()->set('nombre', 'X')->set('categoria_padre_id', 99999)->call('guardar')->assertHasErrors(['categoria_padre_id']);
        $formulario()->set('nombre', 'X')->set('categoria_padre_id', $sub->id)->call('guardar')->assertHasErrors(['categoria_padre_id']);
    }

    public function test_al_editar_solo_se_renombra_y_queda_en_el_historial(): void
    {
        $admin = $this->admin();
        $grupo = Categoria::factory()->create(['nombre' => 'Semillas']);
        $sub = Categoria::factory()->create(['nombre' => 'Hortalizas', 'categoria_padre_id' => $grupo->id]);

        Livewire::actingAs($admin)
            ->test(CategoriaForm::class, ['categoria' => $sub])
            ->assertSet('nombre', 'Hortalizas')
            ->assertSet('categoria_padre_id', $grupo->id)
            ->set('nombre', 'Hortalizas de hoja')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('categorias.index'));

        $sub->refresh();
        $this->assertSame('Hortalizas de hoja', $sub->nombre);
        $this->assertSame($grupo->id, $sub->categoria_padre_id);
        $this->assertDatabaseHas('auditoria_cambios', ['auditable_type' => Categoria::class, 'auditable_id' => $sub->id, 'user_id' => $admin->id, 'campo' => 'nombre']);
    }

    public function test_al_editar_no_se_puede_cambiar_el_grupo_manipulando_el_formulario(): void
    {
        $a = Categoria::factory()->create();
        $b = Categoria::factory()->create();
        $sub = Categoria::factory()->create(['categoria_padre_id' => $a->id]);

        Livewire::actingAs($this->admin())
            ->test(CategoriaForm::class, ['categoria' => $sub])
            ->set('categoria_padre_id', $b->id)
            ->call('guardar');

        $this->assertSame($a->id, $sub->fresh()->categoria_padre_id);
    }

    // ---------- Integración ----------

    public function test_una_categoria_nueva_ya_se_puede_usar_en_el_formulario_de_productos(): void
    {
        $grupo = Categoria::factory()->create(['nombre' => 'Semillas']);
        $sub = Categoria::factory()->create(['nombre' => 'Hortalizas', 'categoria_padre_id' => $grupo->id]);

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class)
            ->set('codigo', 'SEM-1')
            ->set('nombre', 'Semilla de lechuga')
            ->set('categoria_id', $sub->id)
            ->set('unidad_medida', 'kg')
            ->set('stock_minimo', 1)
            ->set('precio', '5')
            ->set('precio_costo', '3')
            ->call('guardar')
            ->assertHasNoErrors();
    }

    public function test_la_auditoria_sabe_nombrar_las_categorias(): void
    {
        $this->assertSame('Categoría', AuditoriaEtiquetas::entidad(Categoria::class));
        $this->assertArrayHasKey('categoria', AuditoriaEtiquetas::ENTIDADES);
        $this->assertSame('Fungicida', AuditoriaEtiquetas::registro(Categoria::factory()->make(['nombre' => 'Fungicida'])));
    }
}
