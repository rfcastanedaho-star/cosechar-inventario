<?php

namespace Tests\Feature;

use App\Livewire\ProveedorForm;
use App\Livewire\ProveedorLista;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProveedorEdicionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_administrador_cambia_nombre_y_ruc_y_queda_en_el_historial(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $proveedor = Proveedor::factory()->create(['nombre' => 'Agro Viejo', 'ruc' => '20111111111']);

        Livewire::actingAs($admin)
            ->test(ProveedorForm::class, ['proveedor' => $proveedor])
            ->set('nombre', 'Agro Nuevo')
            ->set('ruc', '20222222222')
            ->call('guardar')
            ->assertHasNoErrors();

        $proveedor->refresh();
        $this->assertSame('Agro Nuevo', $proveedor->nombre);
        $this->assertSame('20222222222', $proveedor->ruc);

        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Proveedor::class,
            'auditable_id' => $proveedor->id,
            'campo' => 'ruc',
            'valor_anterior' => '20111111111',
            'valor_nuevo' => '20222222222',
        ]);
    }

    public function test_el_operador_no_puede_cambiar_nombre_ni_ruc_pero_si_el_contacto(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);
        $proveedor = Proveedor::factory()->create(['nombre' => 'Agro SAC', 'ruc' => '20333333333', 'telefono' => '111']);

        Livewire::actingAs($operador)
            ->test(ProveedorForm::class, ['proveedor' => $proveedor])
            ->set('nombre', 'Nombre Falso')
            ->set('ruc', '20999999999')
            ->set('telefono', '987654321')
            ->call('guardar')
            ->assertHasNoErrors();

        $proveedor->refresh();
        $this->assertSame('Agro SAC', $proveedor->nombre);
        $this->assertSame('20333333333', $proveedor->ruc);
        $this->assertSame('987654321', $proveedor->telefono);
    }

    public function test_editar_un_proveedor_permite_conservar_su_propio_ruc(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $proveedor = Proveedor::factory()->create(['ruc' => '20444444444']);

        Livewire::actingAs($admin)
            ->test(ProveedorForm::class, ['proveedor' => $proveedor])
            ->set('telefono', '555')
            ->call('guardar')
            ->assertHasNoErrors();
    }

    public function test_no_permite_usar_el_ruc_de_otro_proveedor(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        Proveedor::factory()->create(['ruc' => '20555555555']);
        $proveedor = Proveedor::factory()->create(['ruc' => '20666666666']);

        Livewire::actingAs($admin)
            ->test(ProveedorForm::class, ['proveedor' => $proveedor])
            ->set('ruc', '20555555555')
            ->call('guardar')
            ->assertHasErrors(['ruc' => 'unique']);
    }

    public function test_solo_el_administrador_desactiva_proveedores(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);
        $admin = User::factory()->create(['rol' => 'administrador']);
        $proveedor = Proveedor::factory()->create();

        Livewire::actingAs($operador)
            ->test(ProveedorLista::class)
            ->call('cambiarEstado', $proveedor->id)
            ->assertForbidden();

        Livewire::actingAs($admin)
            ->test(ProveedorLista::class)
            ->call('cambiarEstado', $proveedor->id);

        $this->assertFalse($proveedor->fresh()->activo);
    }
}
