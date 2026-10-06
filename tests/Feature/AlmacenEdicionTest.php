<?php

namespace Tests\Feature;

use App\Livewire\AlmacenForm;
use App\Livewire\AlmacenLista;
use App\Livewire\CompraForm;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlmacenEdicionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_administrador_edita_todos_los_datos_y_queda_en_el_historial(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $almacen = Almacen::factory()->create(['nombre' => 'Almacén Viejo', 'direccion' => 'Calle 1']);

        Livewire::actingAs($admin)
            ->test(AlmacenForm::class, ['almacen' => $almacen])
            ->assertSet('nombre', 'Almacén Viejo')
            ->set('nombre', 'Almacén Nuevo')
            ->set('direccion', 'Calle 2')
            ->call('guardar')
            ->assertHasNoErrors();

        $almacen->refresh();
        $this->assertSame('Almacén Nuevo', $almacen->nombre);
        $this->assertSame('Calle 2', $almacen->direccion);

        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Almacen::class,
            'auditable_id' => $almacen->id,
            'user_id' => $admin->id,
            'campo' => 'nombre',
            'valor_anterior' => 'Almacén Viejo',
            'valor_nuevo' => 'Almacén Nuevo',
        ]);
    }

    public function test_el_operador_solo_puede_cambiar_datos_de_contacto(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);
        $almacen = Almacen::factory()->create(['nombre' => 'Almacén Norte', 'direccion' => 'Calle 1', 'encargado' => 'Luis']);

        Livewire::actingAs($operador)
            ->test(AlmacenForm::class, ['almacen' => $almacen])
            ->set('nombre', 'Nombre Manipulado')
            ->set('direccion', 'Calle 99')
            ->set('encargado', 'María')
            ->call('guardar')
            ->assertHasNoErrors();

        $almacen->refresh();
        $this->assertSame('Almacén Norte', $almacen->nombre);
        $this->assertSame('Calle 99', $almacen->direccion);
        $this->assertSame('María', $almacen->encargado);
    }

    public function test_el_administrador_puede_desactivar_un_almacen_vacio(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $almacen = Almacen::factory()->create();

        Livewire::actingAs($admin)
            ->test(AlmacenLista::class)
            ->call('cambiarEstado', $almacen->id)
            ->assertHasNoErrors();

        $this->assertFalse($almacen->fresh()->activo);
        $this->assertDatabaseHas('auditoria_cambios', ['auditable_id' => $almacen->id, 'campo' => 'activo']);
    }

    public function test_no_se_desactiva_un_almacen_con_stock(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create();
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 10]);

        Livewire::actingAs($admin)
            ->test(AlmacenLista::class)
            ->call('cambiarEstado', $almacen->id)
            ->assertSet('errorEstado', 'No se puede desactivar: el almacén todavía tiene stock.');

        $this->assertTrue($almacen->fresh()->activo);
    }

    public function test_el_operador_no_puede_desactivar_almacenes(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);
        $almacen = Almacen::factory()->create();

        Livewire::actingAs($operador)
            ->test(AlmacenLista::class)
            ->call('cambiarEstado', $almacen->id)
            ->assertForbidden();

        $this->assertTrue($almacen->fresh()->activo);
    }

    public function test_un_almacen_desactivado_no_aparece_al_registrar_compras(): void
    {
        $usuario = User::factory()->create();
        Almacen::factory()->create(['nombre' => 'Almacén Cerrado', 'activo' => false]);
        Almacen::factory()->create(['nombre' => 'Almacén Abierto']);

        $nombres = Livewire::actingAs($usuario)
            ->test(CompraForm::class)
            ->instance()
            ->almacenes
            ->pluck('nombre');

        $this->assertTrue($nombres->contains('Almacén Abierto'));
        $this->assertFalse($nombres->contains('Almacén Cerrado'));
    }
}
