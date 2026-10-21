<?php

namespace Tests\Feature;

use App\Livewire\ClienteForm;
use App\Livewire\ClienteLista;
use App\Livewire\VentaForm;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Support\AuditoriaEtiquetas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ClientesTest extends TestCase
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

    public function test_los_visitantes_son_enviados_al_login(): void
    {
        $this->get(route('clientes.index'))->assertRedirect(route('login'));
    }

    public function test_cualquier_usuario_autenticado_ve_la_lista_y_el_menu(): void
    {
        $this->actingAs($this->operador())
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Clientes')
            ->assertSee(route('clientes.index'), false);
    }

    // ---------- Lista ----------

    public function test_la_lista_busca_filtra_por_estado_y_cambia_el_estado_con_historial(): void
    {
        $admin = $this->admin();
        $activo = Cliente::factory()->create(['nombre' => 'Ana Pérez', 'documento' => '12345678']);
        Cliente::factory()->create(['nombre' => 'Luis Retirado', 'activo' => false]);

        $lista = Livewire::actingAs($admin)->test(ClienteLista::class);

        $lista->assertSee('Ana Pérez')->assertSee('Luis Retirado');
        $lista->set('buscar', '1234')->assertSee('Ana Pérez')->assertDontSee('Luis Retirado');
        $lista->call('limpiarFiltros')->set('estado', 'inactivo')->assertSee('Luis Retirado')->assertDontSee('Ana Pérez');

        $lista->call('cambiarEstado', $activo->id);
        $this->assertFalse($activo->fresh()->activo);
        $this->assertDatabaseHas('auditoria_cambios', ['auditable_type' => Cliente::class, 'auditable_id' => $activo->id, 'campo' => 'activo', 'user_id' => $admin->id]);
    }

    public function test_solo_el_administrador_cambia_el_estado(): void
    {
        $cliente = Cliente::factory()->create();

        Livewire::actingAs($this->operador())
            ->test(ClienteLista::class)
            ->call('cambiarEstado', $cliente->id)
            ->assertForbidden();

        $this->assertTrue($cliente->fresh()->activo);
    }

    public function test_la_lista_pagina_de_a_15(): void
    {
        Cliente::factory()->count(20)->create();

        $lista = Livewire::actingAs($this->admin())->test(ClienteLista::class);

        $this->assertCount(15, $lista->instance()->clientes->items());
        $lista->assertSee('Mostrando 1–15 de 20');
    }

    // ---------- Formulario ----------

    private function formulario(?User $usuario = null, array $parametros = []): Testable
    {
        return Livewire::actingAs($usuario ?? $this->operador())->test(ClienteForm::class, $parametros);
    }

    public function test_cualquier_usuario_registra_un_cliente(): void
    {
        $this->formulario()
            ->set('nombre', 'Ana Pérez')
            ->set('documento', '12345678')
            ->set('telefono', '987654321')
            ->set('direccion', 'Av. Perú 100')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('clientes.index'));

        $this->assertDatabaseHas('clientes', ['nombre' => 'Ana Pérez', 'documento' => '12345678', 'activo' => true]);
    }

    public function test_solo_el_nombre_es_obligatorio(): void
    {
        $this->formulario()->set('nombre', 'Cliente de mostrador')->call('guardar')->assertHasNoErrors();

        $this->assertDatabaseHas('clientes', ['nombre' => 'Cliente de mostrador', 'documento' => null]);
    }

    public function test_valida_nombre_y_formato_del_documento(): void
    {
        $this->formulario()->set('nombre', '')->call('guardar')->assertHasErrors(['nombre' => 'required']);
        $this->formulario()->set('nombre', 'X')->set('documento', 'doc con espacios!')->call('guardar')->assertHasErrors(['documento']);
        $this->formulario()->set('nombre', 'X')->set('telefono', str_repeat('9', 40))->call('guardar')->assertHasErrors(['telefono']);
    }

    public function test_el_documento_no_se_repite_pero_puede_quedar_vacio_en_varios(): void
    {
        Cliente::factory()->create(['documento' => '12345678']);

        $this->formulario()->set('nombre', 'Otro')->set('documento', '12345678')->call('guardar')->assertHasErrors(['documento' => 'unique']);

        $this->formulario()->set('nombre', 'Sin doc 1')->call('guardar')->assertHasNoErrors();
        $this->formulario()->set('nombre', 'Sin doc 2')->call('guardar')->assertHasNoErrors();
    }

    public function test_el_administrador_edita_todo_y_queda_en_el_historial(): void
    {
        $admin = $this->admin();
        $cliente = Cliente::factory()->create(['nombre' => 'Ana Vieja', 'documento' => '11111111', 'telefono' => '900000000']);

        $this->formulario($admin, ['cliente' => $cliente])
            ->assertSet('nombre', 'Ana Vieja')
            ->set('nombre', 'Ana Nueva')
            ->set('documento', '22222222')
            ->set('telefono', '911111111')
            ->call('guardar')
            ->assertHasNoErrors();

        $cliente->refresh();
        $this->assertSame('Ana Nueva', $cliente->nombre);
        $this->assertSame('22222222', $cliente->documento);
        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Cliente::class, 'auditable_id' => $cliente->id, 'campo' => 'nombre', 'valor_anterior' => 'Ana Vieja', 'valor_nuevo' => 'Ana Nueva',
        ]);
    }

    public function test_el_operador_solo_cambia_telefono_y_direccion(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Ana Original', 'documento' => '11111111', 'telefono' => '900000000']);

        $this->formulario($this->operador(), ['cliente' => $cliente])
            ->set('nombre', 'Nombre Manipulado')
            ->set('documento', '99999999')
            ->set('telefono', '955555555')
            ->set('direccion', 'Calle Nueva 5')
            ->call('guardar')
            ->assertHasNoErrors();

        $cliente->refresh();
        $this->assertSame('Ana Original', $cliente->nombre);
        $this->assertSame('11111111', $cliente->documento);
        $this->assertSame('955555555', $cliente->telefono);
        $this->assertSame('Calle Nueva 5', $cliente->direccion);
    }

    public function test_editar_permite_conservar_el_propio_documento(): void
    {
        $cliente = Cliente::factory()->create(['documento' => '11111111']);

        $this->formulario($this->admin(), ['cliente' => $cliente])->call('guardar')->assertHasNoErrors();
    }

    // ---------- Auditoría ----------

    public function test_la_auditoria_sabe_nombrar_a_los_clientes(): void
    {
        $this->assertSame('Cliente', AuditoriaEtiquetas::entidad(Cliente::class));
        $this->assertSame('Documento', AuditoriaEtiquetas::campo('documento'));
        $this->assertArrayHasKey('cliente', AuditoriaEtiquetas::ENTIDADES);
        $this->assertSame('Ana Pérez', AuditoriaEtiquetas::registro(Cliente::factory()->make(['nombre' => 'Ana Pérez'])));
    }

    // ---------- Ventas ----------

    private function venta(): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(VentaForm::class);
    }

    public function test_la_venta_sugiere_clientes_registrados_por_nombre_o_documento(): void
    {
        Cliente::factory()->create(['nombre' => 'Ana Pérez', 'documento' => '12345678']);
        Cliente::factory()->create(['nombre' => 'Luis Gómez', 'documento' => '87654321']);
        Cliente::factory()->create(['nombre' => 'Ana Retirada', 'activo' => false]);

        $this->venta()
            ->set('buscarCliente', 'an')
            ->assertSee('Ana Pérez')
            ->assertDontSee('Luis Gómez')
            ->assertDontSee('Ana Retirada')
            ->set('buscarCliente', '8765')
            ->assertSee('Luis Gómez');
    }

    public function test_elegir_un_cliente_rellena_nombre_y_documento(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Ana Pérez', 'documento' => '12345678']);

        $this->venta()
            ->set('buscarCliente', 'ana')
            ->call('elegirCliente', $cliente->id)
            ->assertSet('cliente_id', $cliente->id)
            ->assertSet('cliente_nombre', 'Ana Pérez')
            ->assertSet('cliente_documento', '12345678')
            ->assertSet('buscarCliente', '')
            ->assertSee('Cliente registrado');
    }

    public function test_enter_elige_el_primer_cliente_y_sin_coincidencias_no_hace_nada(): void
    {
        $primero = Cliente::factory()->create(['nombre' => 'Ana Pérez']);

        $this->venta()->set('buscarCliente', 'ana')->call('elegirPrimerCliente')->assertSet('cliente_id', $primero->id);
        $this->venta()->set('buscarCliente', 'zzz')->call('elegirPrimerCliente')->assertSet('cliente_id', null);
    }

    public function test_no_se_puede_elegir_un_cliente_desactivado_ni_inexistente(): void
    {
        $inactivo = Cliente::factory()->create(['activo' => false]);

        $this->venta()
            ->call('elegirCliente', $inactivo->id)
            ->assertSet('cliente_id', null)
            ->call('elegirCliente', 99999)
            ->assertSet('cliente_id', null);
    }

    public function test_si_se_edita_a_mano_el_nombre_o_el_documento_deja_de_ser_cliente_registrado(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Ana Pérez', 'documento' => '12345678']);

        $this->venta()
            ->call('elegirCliente', $cliente->id)
            ->set('cliente_nombre', 'Otra persona')
            ->assertSet('cliente_id', null);

        $this->venta()
            ->call('elegirCliente', $cliente->id)
            ->set('cliente_documento', '00000000')
            ->assertSet('cliente_id', null);
    }

    public function test_limpiar_cliente_vacia_todo(): void
    {
        $cliente = Cliente::factory()->create();

        $this->venta()
            ->call('elegirCliente', $cliente->id)
            ->call('limpiarCliente')
            ->assertSet('cliente_id', null)
            ->assertSet('cliente_nombre', '')
            ->assertSet('cliente_documento', '');
    }

    public function test_la_venta_guarda_el_cliente_registrado_y_conserva_su_nombre_en_la_venta(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false, 'precio' => 10]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 30]);
        $cliente = Cliente::factory()->create(['nombre' => 'Ana Pérez', 'documento' => '12345678']);

        $this->venta()
            ->call('elegirCliente', $cliente->id)
            ->set('almacen_id', $almacen->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.cantidad', 2)
            ->call('guardar')
            ->assertHasNoErrors();

        $venta = Venta::latest('id')->firstOrFail();
        $this->assertSame($cliente->id, $venta->cliente_id);
        $this->assertSame('Ana Pérez', $venta->cliente_nombre);

        // Si el cliente cambia de nombre después, la venta guarda cómo se llamaba entonces.
        $cliente->update(['nombre' => 'Ana Pérez de Gómez']);
        $this->assertSame('Ana Pérez', $venta->fresh()->cliente_nombre);
        $this->assertSame('Ana Pérez de Gómez', $venta->fresh()->cliente->nombre);
    }

    public function test_se_puede_vender_a_alguien_no_registrado_como_antes(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false, 'precio' => 10]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 30]);

        $this->venta()
            ->set('cliente_nombre', 'Cliente de paso')
            ->set('almacen_id', $almacen->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.cantidad', 1)
            ->call('guardar')
            ->assertHasNoErrors();

        $venta = Venta::latest('id')->firstOrFail();
        $this->assertNull($venta->cliente_id);
        $this->assertSame('Cliente de paso', $venta->cliente_nombre);
    }

    public function test_tras_guardar_la_venta_se_limpia_el_cliente_elegido(): void
    {
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false, 'precio' => 10]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 30]);
        $cliente = Cliente::factory()->create();

        $this->venta()
            ->call('elegirCliente', $cliente->id)
            ->set('almacen_id', $almacen->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.cantidad', 1)
            ->call('guardar')
            ->assertSet('cliente_id', null)
            ->assertSet('cliente_nombre', '');
    }
}
