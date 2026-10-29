<?php

namespace Tests\Feature;

use App\Livewire\ClienteForm;
use App\Livewire\CompraLista;
use App\Livewire\ProveedorForm;
use App\Livewire\VentaLista;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CorregirProveedorYClienteDesdeHistorialTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    // ---------- Menú ----------

    public function test_el_menu_ya_no_lista_proveedores_ni_clientes(): void
    {
        $this->actingAs($this->usuario())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('compras.index'), false)
            ->assertSee(route('ventas.index'), false)
            ->assertDontSee(route('proveedores.index'), false)
            ->assertDontSee(route('clientes.index'), false);
    }

    // ---------- Enlaces desde los historiales ----------

    public function test_el_historial_de_compras_ofrece_corregir_al_proveedor(): void
    {
        $compra = Compra::factory()->create();

        Livewire::actingAs($this->usuario())
            ->test(CompraLista::class)
            ->assertSeeHtml(route('proveedores.editar', ['proveedor' => $compra->proveedor_id, 'desde' => 'compras']))
            ->assertSee('Corregir proveedor');
    }

    public function test_el_historial_de_ventas_ofrece_corregir_al_cliente_registrado(): void
    {
        $cliente = Cliente::factory()->create();
        Venta::factory()->create(['cliente_id' => $cliente->id, 'cliente_nombre' => $cliente->nombre]);

        Livewire::actingAs($this->usuario())
            ->test(VentaLista::class)
            ->assertSeeHtml(route('clientes.editar', ['cliente' => $cliente->id, 'desde' => 'ventas']))
            ->assertSee('Corregir cliente');
    }

    public function test_una_venta_sin_cliente_registrado_no_ofrece_corregirlo(): void
    {
        Venta::factory()->create(['cliente_id' => null, 'cliente_nombre' => 'Cliente varios']);

        Livewire::actingAs($this->usuario())
            ->test(VentaLista::class)
            ->assertSee('Cliente varios')
            ->assertDontSee('Corregir cliente');
    }

    // ---------- Al guardar, se vuelve al historial de donde se vino ----------

    public function test_al_corregir_un_proveedor_desde_compras_se_vuelve_al_historial_de_compras(): void
    {
        Livewire::actingAs($this->usuario())
            ->withQueryParams(['desde' => 'compras'])
            ->test(ProveedorForm::class, ['proveedor' => Proveedor::factory()->create()])
            ->set('telefono', '999')
            ->call('guardar')
            ->assertRedirect(route('compras.historial'));
    }

    public function test_al_corregir_un_cliente_desde_ventas_se_vuelve_al_historial_de_ventas(): void
    {
        Livewire::actingAs($this->usuario())
            ->withQueryParams(['desde' => 'ventas'])
            ->test(ClienteForm::class, ['cliente' => Cliente::factory()->create()])
            ->set('telefono', '999')
            ->call('guardar')
            ->assertRedirect(route('ventas.historial'));
    }

    public function test_un_origen_desconocido_vuelve_al_directorio(): void
    {
        Livewire::actingAs($this->usuario())
            ->withQueryParams(['desde' => 'https://sitio-malo.test'])
            ->test(ProveedorForm::class, ['proveedor' => Proveedor::factory()->create()])
            ->set('telefono', '999')
            ->call('guardar')
            ->assertRedirect(route('proveedores.index'));
    }

    public function test_el_boton_volver_del_formulario_respeta_el_origen(): void
    {
        Livewire::actingAs($this->usuario())
            ->withQueryParams(['desde' => 'compras'])
            ->test(ProveedorForm::class, ['proveedor' => Proveedor::factory()->create()])
            ->assertSeeHtml('href="'.route('compras.historial').'"')
            ->assertSee('Volver al historial de compras');
    }
}
