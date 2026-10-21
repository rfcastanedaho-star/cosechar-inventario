<?php

namespace Tests\Feature;

use App\Livewire\AuditoriaLista;
use App\Livewire\ProductoForm;
use App\Models\Almacen;
use App\Models\AuditoriaCambio;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditoriaPantallaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $atributos = []): User
    {
        return User::factory()->create($atributos + ['rol' => 'administrador']);
    }

    private function cambio(User $usuario, string $tipo, int $id, string $campo, ?string $antes, ?string $despues, string $fecha = '2026-10-10 09:30:00'): AuditoriaCambio
    {
        $cambio = AuditoriaCambio::create([
            'auditable_type' => $tipo, 'auditable_id' => $id, 'user_id' => $usuario->id,
            'campo' => $campo, 'valor_anterior' => $antes, 'valor_nuevo' => $despues,
        ]);
        $cambio->forceFill(['created_at' => $fecha])->save();

        return $cambio;
    }

    public function test_solo_el_administrador_ve_la_auditoria(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'operador_almacen']))->get(route('auditoria.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('auditoria.index'))->assertOk()->assertSee('Historial de cambios');
    }

    public function test_los_visitantes_son_enviados_al_login(): void
    {
        $this->get(route('auditoria.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_muestra_auditoria_solo_al_administrador(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))->assertSee(route('auditoria.index'), false);
        $this->actingAs(User::factory()->create(['rol' => 'operador_almacen']))->get(route('dashboard'))->assertDontSee(route('auditoria.index'), false);
    }

    public function test_muestra_fecha_responsable_registro_campo_y_valores(): void
    {
        $responsable = $this->admin(['name' => 'Rosa Responsable']);
        $producto = Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'FUN-001']);
        $this->cambio($responsable, Producto::class, $producto->id, 'nombre', 'Fungicida Viejo', 'Fungicida Cobre');

        Livewire::actingAs($this->admin())
            ->test(AuditoriaLista::class)
            ->assertSee('10/10/2026 09:30')
            ->assertSee('Rosa Responsable')
            ->assertSee('Producto')
            ->assertSee('FUN-001')
            ->assertSee('Fungicida Cobre')
            ->assertSee('Nombre')
            ->assertSee('Fungicida Viejo');
    }

    public function test_los_valores_se_muestran_en_lenguaje_comun(): void
    {
        $producto = Producto::factory()->create();
        $categoria = Categoria::factory()->create(['nombre' => 'Fungicida de prueba']);
        $usuario = $this->admin();
        $this->cambio($usuario, Producto::class, $producto->id, 'activo', '1', '0');
        $this->cambio($usuario, Producto::class, $producto->id, 'precio', '40.00', '55.9');
        $this->cambio($usuario, Producto::class, $producto->id, 'categoria_id', null, (string) $categoria->id);
        $this->cambio($usuario, User::class, $usuario->id, 'rol', 'operador_almacen', 'administrador');

        Livewire::actingAs($this->admin())
            ->test(AuditoriaLista::class)
            ->assertSee('Estado')
            ->assertSee('Activo')
            ->assertSee('Inactivo')
            ->assertSee('S/ 40.00')
            ->assertSee('S/ 55.90')
            ->assertSee('Fungicida de prueba')
            ->assertSee('Operador de almacén')
            ->assertSee('Administrador');
    }

    public function test_un_cambio_de_contrasena_no_muestra_ningun_valor_secreto(): void
    {
        $usuario = $this->admin();
        $this->cambio($usuario, User::class, $usuario->id, 'password', null, '(contraseña cambiada)');

        Livewire::actingAs($this->admin())
            ->test(AuditoriaLista::class)
            ->assertSee('Contraseña')
            ->assertSee('(contraseña cambiada)')
            ->assertDontSee('$2y$');
    }

    public function test_si_el_registro_ya_no_existe_lo_indica(): void
    {
        $this->cambio($this->admin(), Producto::class, 99999, 'nombre', 'A', 'B');

        Livewire::actingAs($this->admin())
            ->test(AuditoriaLista::class)
            ->assertSee('(registro eliminado)');
    }

    public function test_filtra_por_entidad_usuario_y_fechas(): void
    {
        $ana = $this->admin(['name' => 'Ana Admin']);
        $luis = $this->admin(['name' => 'Luis Admin']);
        $producto = Producto::factory()->create(['nombre' => 'Producto Auditado']);
        $almacen = Almacen::factory()->create(['nombre' => 'Almacén Auditado']);
        $this->cambio($ana, Producto::class, $producto->id, 'nombre', 'A', 'B', '2026-10-02 09:00:00');
        $this->cambio($luis, Almacen::class, $almacen->id, 'nombre', 'C', 'D', '2026-10-08 09:00:00');

        $lista = Livewire::actingAs($this->admin())->test(AuditoriaLista::class);

        $lista->set('entidad', 'almacen')->assertSee('Almacén Auditado')->assertDontSee('Producto Auditado');
        $lista->set('entidad', '')->set('usuario_id', $ana->id)->assertSee('Producto Auditado')->assertDontSee('Almacén Auditado');
        $lista->set('usuario_id', null)->set('desde', '2026-10-05')->assertSee('Almacén Auditado')->assertDontSee('Producto Auditado');
        $lista->set('desde', '')->set('hasta', '2026-10-05')->assertSee('Producto Auditado')->assertDontSee('Almacén Auditado');
    }

    public function test_limpiar_quita_todos_los_filtros(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AuditoriaLista::class)
            ->set('entidad', 'producto')
            ->set('usuario_id', 3)
            ->set('desde', '2026-01-01')
            ->set('hasta', '2026-12-31')
            ->call('limpiarFiltros')
            ->assertSet('entidad', '')
            ->assertSet('usuario_id', null)
            ->assertSet('desde', '')
            ->assertSet('hasta', '');
    }

    public function test_pagina_de_a_20_mostrando_primero_lo_mas_reciente(): void
    {
        $admin = $this->admin();
        foreach (range(1, 25) as $n) {
            $this->cambio($admin, Producto::class, 1, 'nombre', 'v'.$n, 'v'.($n + 1), now()->subMinutes(100 - $n)->toDateTimeString());
        }

        $lista = Livewire::actingAs($admin)->test(AuditoriaLista::class);

        $this->assertCount(20, $lista->instance()->cambios->items());
        $this->assertSame(25, $lista->instance()->cambios->total());
        $this->assertSame('v26', $lista->instance()->cambios->items()[0]->valor_nuevo);
    }

    public function test_cambiar_un_filtro_vuelve_a_la_primera_pagina(): void
    {
        $admin = $this->admin();
        foreach (range(1, 25) as $n) {
            $this->cambio($admin, Producto::class, 1, 'nombre', 'a', 'b');
        }

        Livewire::actingAs($admin)
            ->test(AuditoriaLista::class)
            ->call('gotoPage', 2)
            ->set('entidad', 'producto')
            ->assertSet('paginators.page', 1);
    }

    public function test_avisa_cuando_no_hay_cambios_o_ninguno_coincide(): void
    {
        $lista = Livewire::actingAs($this->admin())->test(AuditoriaLista::class)->assertSee('Todavía no hay cambios registrados');

        $this->cambio($this->admin(), Producto::class, 1, 'nombre', 'a', 'b');

        $lista->set('entidad', 'almacen')->assertSee('No se encontraron cambios con esos filtros');
    }

    public function test_ignora_filtros_con_valores_no_permitidos(): void
    {
        $this->cambio($this->admin(), Producto::class, 1, 'nombre', 'a', 'b');

        Livewire::actingAs($this->admin())
            ->test(AuditoriaLista::class)
            ->set('entidad', 'cualquier-cosa')
            ->set('desde', 'no-es-fecha')
            ->assertOk()
            ->assertSee('Historial de cambios');
    }

    public function test_un_operador_no_puede_abrir_el_componente(): void
    {
        Livewire::actingAs(User::factory()->create(['rol' => 'operador_almacen']))
            ->test(AuditoriaLista::class)
            ->assertForbidden();
    }

    public function test_lo_que_edita_el_administrador_aparece_en_el_historial(): void
    {
        $admin = $this->admin(['name' => 'Rosa Editora']);
        $producto = Producto::factory()->create(['nombre' => 'Nombre Original', 'codigo' => 'AUD-001']);

        Livewire::actingAs($admin)
            ->test(ProductoForm::class, ['producto' => $producto])
            ->set('nombre', 'Nombre Corregido')
            ->call('guardar');

        Livewire::actingAs($admin)
            ->test(AuditoriaLista::class)
            ->assertSee('Rosa Editora')
            ->assertSee('AUD-001')
            ->assertSee('Nombre Original')
            ->assertSee('Nombre Corregido');
    }
}
