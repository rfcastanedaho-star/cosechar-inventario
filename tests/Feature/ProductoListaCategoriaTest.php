<?php

namespace Tests\Feature;

use App\Livewire\ProductoLista;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoListaCategoriaTest extends TestCase
{
    use RefreshDatabase;

    private Categoria $agroquimicos;

    private Categoria $fungicida;

    private Categoria $insecticida;

    private Categoria $herramientas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agroquimicos = Categoria::factory()->create(['nombre' => 'Agroquímicos']);
        $this->fungicida = Categoria::factory()->create(['nombre' => 'Fungicida', 'categoria_padre_id' => $this->agroquimicos->id]);
        $this->insecticida = Categoria::factory()->create(['nombre' => 'Insecticida', 'categoria_padre_id' => $this->agroquimicos->id]);
        $this->herramientas = Categoria::factory()->create(['nombre' => 'Herramientas']);
    }

    private function lista(): Testable
    {
        return Livewire::actingAs(User::factory()->create(['rol' => 'operador_almacen']))->test(ProductoLista::class);
    }

    private function producto(string $nombre, Categoria $categoria, array $extra = []): Producto
    {
        return Producto::factory()->create(['nombre' => $nombre, 'categoria_id' => $categoria->id] + $extra);
    }

    /** @return array<int, string> */
    private function nombres(Testable $lista): array
    {
        return $lista->instance()->productos->pluck('nombre')->all();
    }

    public function test_sin_elegir_categoria_se_ven_todos_los_productos(): void
    {
        $this->producto('Antracol', $this->fungicida);
        $this->producto('Pala de punta', $this->herramientas);

        $this->assertEqualsCanonicalizing(['Antracol', 'Pala de punta'], $this->nombres($this->lista()));
    }

    public function test_filtra_por_una_subcategoria(): void
    {
        $this->producto('Antracol', $this->fungicida);
        $this->producto('Karate', $this->insecticida);
        $this->producto('Pala de punta', $this->herramientas);

        $this->assertSame(['Antracol'], $this->nombres($this->lista()->set('categoria', $this->fungicida->id)));
    }

    public function test_filtra_por_un_grupo_e_incluye_todas_sus_subcategorias(): void
    {
        $this->producto('Antracol', $this->fungicida);
        $this->producto('Karate', $this->insecticida);
        $this->producto('Pala de punta', $this->herramientas);

        $this->assertEqualsCanonicalizing(['Antracol', 'Karate'], $this->nombres($this->lista()->set('categoria', $this->agroquimicos->id)));
    }

    public function test_se_combina_con_el_buscador_y_con_los_inactivos(): void
    {
        $this->producto('Antracol 70', $this->fungicida);
        $this->producto('Cobre', $this->fungicida);
        $this->producto('Viejo', $this->fungicida, ['activo' => false]);

        $lista = $this->lista()->set('categoria', $this->fungicida->id);

        $this->assertEqualsCanonicalizing(['Antracol 70', 'Cobre'], $this->nombres($lista));
        $this->assertSame(['Antracol 70'], $this->nombres($lista->set('buscar', 'antra')));
        $this->assertEqualsCanonicalizing(['Antracol 70', 'Cobre', 'Viejo'], $this->nombres($lista->set('buscar', '')->set('mostrarInactivos', true)));
    }

    public function test_volver_a_todas_las_categorias_quita_el_filtro(): void
    {
        $this->producto('Antracol', $this->fungicida);
        $this->producto('Pala de punta', $this->herramientas);

        $lista = $this->lista()->set('categoria', $this->fungicida->id);
        $this->assertCount(1, $this->nombres($lista));

        $this->assertCount(2, $this->nombres($lista->set('categoria', '')));
    }

    public function test_el_desplegable_ofrece_los_grupos_con_sus_subcategorias(): void
    {
        $this->lista()
            ->assertSee('Todas las categorías')
            ->assertSeeInOrder(['Agroquímicos', 'Fungicida', 'Insecticida', 'Herramientas']);
    }

    public function test_avisa_cuando_la_categoria_no_tiene_productos_y_permite_quitar_el_filtro(): void
    {
        $this->producto('Pala de punta', $this->herramientas);

        $this->lista()
            ->set('categoria', $this->fungicida->id)
            ->assertSee('No se encontraron productos con esos filtros.')
            ->assertSee('Quitar filtros')
            ->call('limpiarFiltros')
            ->assertSet('categoria', null)
            ->assertSet('buscar', '')
            ->assertDontSee('No se encontraron productos');
    }

    public function test_el_filtro_viaja_en_la_direccion_para_poder_compartirla(): void
    {
        $this->producto('Antracol', $this->fungicida);
        $this->producto('Pala de punta', $this->herramientas);

        Livewire::actingAs(User::factory()->create())
            ->withQueryParams(['categoria' => $this->herramientas->id])
            ->test(ProductoLista::class)
            ->assertSee('Pala de punta')
            ->assertDontSee('Antracol');
    }
}
