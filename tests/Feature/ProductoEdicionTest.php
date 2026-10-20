<?php

namespace Tests\Feature;

use App\Livewire\ProductoForm;
use App\Models\Categoria;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoEdicionTest extends TestCase
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

    public function test_la_ruta_de_edicion_carga_el_producto(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre']);

        $this->actingAs($this->admin())
            ->get(route('productos.editar', $producto))
            ->assertOk()
            ->assertSee('Editar producto');
    }

    public function test_el_formulario_de_edicion_muestra_los_datos_actuales(): void
    {
        $producto = Producto::factory()->create([
            'codigo' => 'FUN-001', 'nombre' => 'Fungicida Cobre', 'precio' => 45.5, 'stock_minimo' => 12,
            'unidad_medida' => 'litro', 'maneja_vencimiento' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->assertSet('codigo', 'FUN-001')
            ->assertSet('nombre', 'Fungicida Cobre')
            ->assertSet('categoria_id', $producto->categoria_id)
            ->assertSet('unidad_medida', 'litro')
            ->assertSet('stock_minimo', 12)
            ->assertSet('maneja_vencimiento', true);
    }

    public function test_el_administrador_edita_todos_los_datos_y_queda_en_el_historial(): void
    {
        $admin = $this->admin();
        $producto = Producto::factory()->create([
            'codigo' => 'FUN-001', 'nombre' => 'Fungicida Viejo', 'precio' => 40, 'unidad_medida' => 'litro', 'stock_minimo' => 5,
        ]);
        $otraCategoria = Categoria::factory()->create();

        Livewire::actingAs($admin)
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('codigo', 'FUN-002')
            ->set('nombre', 'Fungicida Nuevo')
            ->set('categoria_id', $otraCategoria->id)
            ->set('unidad_medida', 'galon')
            ->set('stock_minimo', 9)
            ->set('precio', '55.90')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('creado', true);

        $producto->refresh();
        $this->assertSame('FUN-002', $producto->codigo);
        $this->assertSame('Fungicida Nuevo', $producto->nombre);
        $this->assertSame($otraCategoria->id, $producto->categoria_id);
        $this->assertSame('galon', $producto->unidad_medida);
        $this->assertSame(9, $producto->stock_minimo);
        $this->assertSame('55.90', $producto->precio);

        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Producto::class,
            'auditable_id' => $producto->id,
            'user_id' => $admin->id,
            'campo' => 'precio',
        ]);
        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Producto::class,
            'auditable_id' => $producto->id,
            'campo' => 'nombre',
            'valor_anterior' => 'Fungicida Viejo',
            'valor_nuevo' => 'Fungicida Nuevo',
        ]);
    }

    public function test_guardar_sin_cambios_no_llena_el_historial(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Igual']);

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('auditoria_cambios', 0);
    }

    public function test_el_formulario_de_edicion_no_se_vacia_despues_de_guardar(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre']);

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('nombre', 'Fungicida Cobre Plus')
            ->call('guardar')
            ->assertSet('nombre', 'Fungicida Cobre Plus')
            ->assertSee('Producto actualizado correctamente');
    }

    public function test_el_codigo_no_puede_repetirse_con_otro_producto_pero_si_conservar_el_propio(): void
    {
        Producto::factory()->create(['codigo' => 'OCUPADO']);
        $producto = Producto::factory()->create(['codigo' => 'MIO-1']);

        $componente = Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => $producto]);

        $componente->set('codigo', 'OCUPADO')->call('guardar')->assertHasErrors(['codigo' => 'unique']);
        $this->assertSame('MIO-1', $producto->fresh()->codigo);

        $componente->set('codigo', 'MIO-1')->call('guardar')->assertHasNoErrors();
    }

    public function test_el_operador_solo_puede_cambiar_el_nombre(): void
    {
        $producto = Producto::factory()->create([
            'codigo' => 'FUN-001', 'nombre' => 'Original', 'precio' => 40, 'unidad_medida' => 'litro', 'stock_minimo' => 5,
        ]);
        $otraCategoria = Categoria::factory()->create();
        $categoriaOriginal = $producto->categoria_id;

        Livewire::actingAs($this->operador())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('nombre', 'Nombre corregido')
            ->set('codigo', 'MANIPULADO')
            ->set('precio', '1.00')
            ->set('unidad_medida', 'kg')
            ->set('stock_minimo', 99)
            ->set('categoria_id', $otraCategoria->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $producto->refresh();
        $this->assertSame('Nombre corregido', $producto->nombre);
        $this->assertSame('FUN-001', $producto->codigo);
        $this->assertSame('40.00', $producto->precio);
        $this->assertSame('litro', $producto->unidad_medida);
        $this->assertSame(5, $producto->stock_minimo);
        $this->assertSame($categoriaOriginal, $producto->categoria_id);
    }

    public function test_si_ya_tiene_lotes_no_se_puede_cambiar_si_maneja_vencimiento(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);
        Lote::factory()->for($producto, 'producto')->create(['cantidad' => 5]);

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('maneja_vencimiento', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertFalse($producto->fresh()->maneja_vencimiento);
    }

    public function test_sin_lotes_el_administrador_si_puede_cambiar_si_maneja_vencimiento(): void
    {
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('maneja_vencimiento', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertTrue($producto->fresh()->maneja_vencimiento);
    }

    public function test_la_edicion_valida_los_mismos_campos_que_el_registro(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('nombre', '')
            ->set('precio', '-3')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required', 'precio' => 'min']);
    }

    public function test_se_puede_cambiar_la_foto_al_editar(): void
    {
        $producto = Producto::factory()->create();
        ProductoImagen::create(['producto_id' => $producto->id, 'mime' => 'image/png', 'contenido' => base64_encode('vieja')]);

        Livewire::actingAs($this->operador())
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('imagen', UploadedFile::fake()->image('nueva.png', 300, 300))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(1, ProductoImagen::where('producto_id', $producto->id)->count());
        $this->assertNotSame(base64_encode('vieja'), ProductoImagen::where('producto_id', $producto->id)->value('contenido'));
    }

    public function test_el_registro_nuevo_sigue_funcionando(): void
    {
        $categoria = Categoria::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ProductoForm::class)
            ->set('codigo', 'NUEVO-1')
            ->set('nombre', 'Producto nuevo')
            ->set('categoria_id', $categoria->id)
            ->set('unidad_medida', 'kg')
            ->set('stock_minimo', 3)
            ->set('precio', '10')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Producto registrado correctamente');

        $this->assertDatabaseHas('productos', ['codigo' => 'NUEVO-1', 'activo' => true]);
    }
}
