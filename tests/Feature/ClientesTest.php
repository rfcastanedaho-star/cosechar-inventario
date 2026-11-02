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
use Illuminate\Support\Facades\Route;
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

    public function test_cualquier_usuario_autenticado_ve_el_directorio_de_clientes(): void
    {
        $this->actingAs($this->operador())
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Clientes');
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

    // ---------- Formulario (registrar y editar) ----------

    private function formulario(?User $usuario = null, array $parametros = []): Testable
    {
        return Livewire::actingAs($usuario ?? $this->operador())->test(ClienteForm::class, $parametros);
    }

    public function test_la_pantalla_para_registrar_clientes_existe_en_el_menu(): void
    {
        $this->assertTrue(Route::has('clientes.nuevo'));

        $this->actingAs($this->admin())->get('/clientes/nuevo')->assertOk()->assertSee('Registrar cliente');
    }

    public function test_la_lista_ofrece_registrar_clientes_y_editarlos(): void
    {
        $cliente = Cliente::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ClienteLista::class)
            ->assertSee('Registrar cliente')
            ->assertSeeHtml(route('clientes.nuevo'))
            ->assertSeeHtml(route('clientes.editar', $cliente))
            ->assertSee('en Ventas solo se elige entre los registrados');
    }

    public function test_se_registra_un_cliente_nuevo_desde_su_formulario(): void
    {
        $this->formulario($this->operador())
            ->set('nombre', '  Ana Pérez ')
            ->set('documento', '12345678')
            ->set('telefono', '900111222')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('clientes.index'));

        $cliente = Cliente::where('documento', '12345678')->firstOrFail();
        $this->assertSame('Ana Pérez', $cliente->nombre);
        $this->assertSame('900111222', $cliente->telefono);
        $this->assertTrue($cliente->activo);
    }

    public function test_para_registrar_solo_el_nombre_es_obligatorio_y_el_documento_no_se_repite(): void
    {
        Cliente::factory()->create(['documento' => '12345678']);

        $this->formulario()->call('guardar')->assertHasErrors(['nombre' => 'required']);
        $this->formulario()->set('nombre', 'Otro')->set('documento', '12345678')->call('guardar')->assertHasErrors(['documento' => 'unique']);
        $this->formulario()->set('nombre', 'Otro')->set('documento', 'doc raro!')->call('guardar')->assertHasErrors(['documento']);

        $this->formulario()->set('nombre', 'Sin documento')->call('guardar')->assertHasNoErrors();
        $this->assertNull(Cliente::where('nombre', 'Sin documento')->firstOrFail()->documento);
    }

    public function test_al_registrar_desde_ventas_se_vuelve_al_formulario_de_venta(): void
    {
        Livewire::actingAs($this->operador())
            ->withQueryParams(['desde' => 'venta'])
            ->test(ClienteForm::class)
            ->set('nombre', 'Ana Pérez')
            ->call('guardar')
            ->assertRedirect(route('ventas.index'));
    }

    public function test_valida_nombre_y_formato_del_documento_al_editar(): void
    {
        $cliente = Cliente::factory()->create();
        $admin = $this->admin();

        $this->formulario($admin, ['cliente' => $cliente])->set('nombre', '')->call('guardar')->assertHasErrors(['nombre' => 'required']);
        $this->formulario($admin, ['cliente' => $cliente])->set('documento', 'doc con espacios!')->call('guardar')->assertHasErrors(['documento']);
        $this->formulario($admin, ['cliente' => $cliente])->set('telefono', str_repeat('9', 40))->call('guardar')->assertHasErrors(['telefono']);
    }

    public function test_el_documento_no_se_repite_pero_puede_quedar_vacio(): void
    {
        Cliente::factory()->create(['documento' => '12345678']);
        $otro = Cliente::factory()->create(['documento' => '87654321']);
        $admin = $this->admin();

        $this->formulario($admin, ['cliente' => $otro])->set('documento', '12345678')->call('guardar')->assertHasErrors(['documento' => 'unique']);
        $this->formulario($admin, ['cliente' => $otro])->set('documento', '')->call('guardar')->assertHasNoErrors();

        $this->assertNull($otro->fresh()->documento);
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

    // ---------- Ventas: el cliente solo se elige entre los registrados ----------

    private function venta(): Testable
    {
        return Livewire::actingAs(User::factory()->create())->test(VentaForm::class);
    }

    /** Venta de una unidad a un cliente ya elegido; devuelve el componente para seguir probando. */
    private function venderA(?int $clienteId): Testable
    {
        $almacen = Almacen::first() ?? Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false, 'precio' => 10]);
        Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 50]);

        $componente = $this->venta();

        if ($clienteId) {
            $componente->call('elegirCliente', $clienteId);
        }

        return $componente
            ->set('almacen_id', $almacen->id)
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.cantidad', 1)
            ->call('guardar');
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

    public function test_la_flechita_abre_la_lista_con_todos_los_clientes_en_orden_alfabetico(): void
    {
        Cliente::factory()->create(['nombre' => 'Zoila Vega']);
        Cliente::factory()->create(['nombre' => 'Ana Pérez']);
        Cliente::factory()->create(['nombre' => 'Retirado', 'activo' => false]);

        $componente = $this->venta()
            ->assertDontSee('Ana Pérez')
            ->call('alternarListaClientes')
            ->assertSet('listaClientes', true)
            ->assertSee('Ana Pérez')
            ->assertSee('Zoila Vega')
            ->assertDontSee('Retirado');

        $this->assertSame(['Ana Pérez', 'Zoila Vega'], $componente->instance()->clientesSugeridos->pluck('nombre')->all());
    }

    public function test_elegir_un_cliente_lo_deja_seleccionado_y_cierra_la_lista(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Ana Pérez', 'documento' => '12345678']);

        $this->venta()
            ->call('alternarListaClientes')
            ->set('buscarCliente', 'ana')
            ->call('elegirCliente', $cliente->id)
            ->assertSet('cliente_id', $cliente->id)
            ->assertSet('buscarCliente', '')
            ->assertSet('listaClientes', false)
            ->assertSee('Ana Pérez')
            ->assertSee('Registrado');
    }

    public function test_enter_elige_el_primer_cliente_y_sin_coincidencias_no_hace_nada(): void
    {
        $primero = Cliente::factory()->create(['nombre' => 'Ana Pérez']);

        $this->venta()->set('buscarCliente', 'ana')->call('elegirPrimerCliente')->assertSet('cliente_id', $primero->id);
        $this->venta()->set('buscarCliente', 'zzz')->call('elegirPrimerCliente')->assertSet('cliente_id', null);
    }

    public function test_sin_coincidencias_manda_a_registrar_el_cliente_en_clientes(): void
    {
        $this->venta()
            ->set('buscarCliente', 'zzz')
            ->assertSee('No hay ningún cliente registrado que coincida')
            ->assertSeeHtml(route('clientes.nuevo', ['desde' => 'venta']));
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

    public function test_limpiar_cliente_vuelve_al_campo_de_busqueda(): void
    {
        $cliente = Cliente::factory()->create();

        $this->venta()
            ->call('elegirCliente', $cliente->id)
            ->call('limpiarCliente')
            ->assertSet('cliente_id', null)
            ->assertSet('buscarCliente', '')
            ->assertSet('listaClientes', false);
    }

    public function test_la_venta_guarda_el_cliente_registrado_y_conserva_su_nombre_en_la_venta(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Ana Pérez', 'documento' => '12345678']);

        $this->venderA($cliente->id)->assertHasNoErrors();

        $venta = Venta::latest('id')->firstOrFail();
        $this->assertSame($cliente->id, $venta->cliente_id);
        $this->assertSame('Ana Pérez', $venta->cliente_nombre);
        $this->assertSame('12345678', $venta->cliente_documento);

        // Si el cliente cambia de nombre después, la venta guarda cómo se llamaba entonces.
        $cliente->update(['nombre' => 'Ana Pérez de Gómez']);
        $this->assertSame('Ana Pérez', $venta->fresh()->cliente_nombre);
        $this->assertSame('Ana Pérez de Gómez', $venta->fresh()->cliente->nombre);
    }

    public function test_sin_cliente_elegido_la_venta_no_se_guarda_y_no_se_crea_ningun_cliente(): void
    {
        $this->venderA(null)->assertHasErrors(['cliente_id' => 'required']);

        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_tras_guardar_la_venta_se_limpia_el_cliente_elegido(): void
    {
        $cliente = Cliente::factory()->create();

        $this->venderA($cliente->id)
            ->assertSet('cliente_id', null)
            ->assertSet('buscarCliente', '');
    }

    public function test_la_pantalla_de_ventas_pide_elegir_el_cliente_y_manda_a_registrarlo_en_clientes(): void
    {
        Livewire::actingAs($this->operador())
            ->test(VentaForm::class)
            ->assertSee('Solo se pueden elegir clientes ya registrados')
            ->assertSeeHtml(route('clientes.nuevo', ['desde' => 'venta']))
            ->assertDontSee('se registra solo');
    }
}
