<?php

namespace Tests\Feature;

use App\Livewire\ProductoForm;
use App\Livewire\ProductoLista;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Repositories\ProductoRepository;
use App\Support\AuditoriaEtiquetas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoCamposNuevosTest extends TestCase
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

    private function nuevo(?User $usuario = null): Testable
    {
        $categoria = Categoria::factory()->create();

        return Livewire::actingAs($usuario ?? $this->admin())
            ->test(ProductoForm::class)
            ->set('codigo', 'NUE-1')
            ->set('nombre', 'Producto nuevo')
            ->set('categoria_id', $categoria->id)
            ->set('unidad_medida', 'litro')
            ->set('stock_minimo', 2)
            ->set('precio', '20');
    }

    // ---------- Registro ----------

    public function test_registra_marca_y_presentacion(): void
    {
        $this->nuevo()
            ->set('marca', 'Bayer')
            ->set('presentacion', 'Frasco 250 ml')
            ->call('guardar')
            ->assertHasNoErrors();

        $producto = Producto::where('codigo', 'NUE-1')->firstOrFail();
        $this->assertSame('Bayer', $producto->marca);
        $this->assertSame('Frasco 250 ml', $producto->presentacion);
    }

    public function test_los_campos_nuevos_son_opcionales_y_quedan_vacios_como_nulos(): void
    {
        $this->nuevo()->call('guardar')->assertHasNoErrors();

        $producto = Producto::where('codigo', 'NUE-1')->firstOrFail();
        $this->assertNull($producto->marca);
        $this->assertNull($producto->presentacion);
    }

    public function test_valida_el_formato_y_la_longitud_de_los_campos_nuevos(): void
    {
        $this->nuevo()->set('marca', str_repeat('a', 101))->call('guardar')->assertHasErrors(['marca' => 'max']);
        $this->nuevo()->set('presentacion', str_repeat('a', 101))->call('guardar')->assertHasErrors(['presentacion' => 'max']);
    }

    public function test_el_formulario_propone_el_primer_codigo_si_no_hay_productos_con_ese_formato(): void
    {
        Livewire::actingAs($this->admin())->test(ProductoForm::class)->assertSet('codigo', 'PRD-0001');
    }

    public function test_propone_el_siguiente_numero_ignorando_codigos_con_otro_formato(): void
    {
        Producto::factory()->create(['codigo' => 'PRD-0007']);
        Producto::factory()->create(['codigo' => 'PRD-0003']);
        Producto::factory()->create(['codigo' => 'FUN-9999']);
        Producto::factory()->create(['codigo' => 'PRD-ABC']);

        $this->assertSame('PRD-0008', (new ProductoRepository)->siguienteCodigo());
        Livewire::actingAs($this->admin())->test(ProductoForm::class)->assertSet('codigo', 'PRD-0008');
    }

    public function test_el_codigo_propuesto_se_puede_cambiar(): void
    {
        $categoria = Categoria::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class)
            ->set('codigo', 'MI-CODIGO')
            ->set('nombre', 'Producto')
            ->set('categoria_id', $categoria->id)
            ->set('unidad_medida', 'kg')
            ->set('stock_minimo', 1)
            ->set('precio', '5')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('productos', ['codigo' => 'MI-CODIGO']);
    }

    public function test_despues_de_guardar_propone_el_codigo_siguiente(): void
    {
        $this->nuevo()
            ->set('codigo', 'PRD-0001')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('codigo', 'PRD-0002');
    }

    public function test_al_editar_no_se_cambia_el_codigo_por_uno_propuesto(): void
    {
        $producto = Producto::factory()->create(['codigo' => 'FUN-001']);

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->assertSet('codigo', 'FUN-001');
    }

    // ---------- Edición ----------

    public function test_el_administrador_edita_los_campos_nuevos_y_queda_en_el_historial(): void
    {
        $admin = $this->admin();
        $producto = Producto::factory()->create(['marca' => 'Marca Vieja', 'precio' => 20]);

        Livewire::actingAs($admin)
            ->test(ProductoForm::class, ['producto' => $producto])
            ->assertSet('marca', 'Marca Vieja')
            ->set('marca', 'Bayer')
            ->set('presentacion', '1 litro')
            ->call('guardar')
            ->assertHasNoErrors();

        $producto->refresh();
        $this->assertSame('Bayer', $producto->marca);
        $this->assertSame('1 litro', $producto->presentacion);
        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Producto::class, 'auditable_id' => $producto->id, 'campo' => 'marca',
            'valor_anterior' => 'Marca Vieja', 'valor_nuevo' => 'Bayer',
        ]);
    }

    public function test_el_operador_no_puede_cambiar_los_campos_nuevos(): void
    {
        $producto = Producto::factory()->create(['marca' => 'Original', 'precio' => 20]);

        Livewire::actingAs($this->operador())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('marca', 'Manipulada')
            ->set('presentacion', 'Manipulada')
            ->call('guardar')
            ->assertHasNoErrors();

        $producto->refresh();
        $this->assertSame('Original', $producto->marca);
        $this->assertNull($producto->presentacion);
    }

    // ---------- Auditoría ----------

    public function test_la_auditoria_muestra_los_campos_nuevos_en_lenguaje_comun(): void
    {
        $this->assertSame('Código de barras', AuditoriaEtiquetas::campo('codigo_barras'));
        $this->assertSame('Marca', AuditoriaEtiquetas::campo('marca'));
        $this->assertSame('Presentación', AuditoriaEtiquetas::campo('presentacion'));
        $this->assertSame('Precio por mayor', AuditoriaEtiquetas::campo('precio_mayor'));
        $this->assertSame('S/ 15.00', AuditoriaEtiquetas::valor('precio_mayor', '15'));
    }

    // ---------- Lista ----------

    public function test_la_lista_muestra_marca_y_presentacion_bajo_el_nombre(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'marca' => 'Bayer', 'presentacion' => 'Frasco 250 ml']);

        Livewire::actingAs($this->admin())
            ->test(ProductoLista::class)
            ->assertSee('Fungicida Cobre')
            ->assertSee('Bayer')
            ->assertSee('Frasco 250 ml');
    }

    public function test_la_lista_busca_tambien_por_marca(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'marca' => 'Bayer']);
        Producto::factory()->create(['nombre' => 'Herbicida Total', 'marca' => 'Basf']);

        $lista = Livewire::actingAs($this->admin())->test(ProductoLista::class);

        $lista->set('buscar', 'bayer')->assertSee('Fungicida Cobre')->assertDontSee('Herbicida Total');
        $lista->set('buscar', 'basf')->assertSee('Herbicida Total')->assertDontSee('Fungicida Cobre');
    }
}
