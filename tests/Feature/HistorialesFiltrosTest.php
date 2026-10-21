<?php

namespace Tests\Feature;

use App\Livewire\AlmacenLista;
use App\Livewire\CompraLista;
use App\Livewire\ProveedorLista;
use App\Livewire\VentaLista;
use App\Models\Almacen;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HistorialesFiltrosTest extends TestCase
{
    use RefreshDatabase;

    private function lista(string $clase): Testable
    {
        return Livewire::actingAs(User::factory()->create(['rol' => 'administrador']))->test($clase);
    }

    /** @return array<string, array{class-string}> */
    public static function listasConPaginacion(): array
    {
        return [
            'compras' => [CompraLista::class],
            'ventas' => [VentaLista::class],
        ];
    }

    private function crear(string $clase, int $cantidad, array $atributos = []): void
    {
        match ($clase) {
            CompraLista::class => Compra::factory()->count($cantidad)->create($atributos),
            VentaLista::class => Venta::factory()->count($cantidad)->create($atributos),
        };
    }

    #[DataProvider('listasConPaginacion')]
    public function test_pagina_de_a_15_y_muestra_el_resumen(string $clase): void
    {
        $this->crear($clase, 20);

        $lista = $this->lista($clase);
        $propiedad = $clase === CompraLista::class ? 'compras' : 'ventas';

        $this->assertCount(15, $lista->instance()->{$propiedad}->items());
        $lista->assertSee('Mostrando 1–15 de 20')->assertSee('Siguiente');
    }

    #[DataProvider('listasConPaginacion')]
    public function test_cambiar_un_filtro_vuelve_a_la_primera_pagina(string $clase): void
    {
        $this->crear($clase, 20);

        $this->lista($clase)
            ->call('gotoPage', 2)
            ->set('estado', 'vigente')
            ->assertSet('paginators.page', 1);
    }

    #[DataProvider('listasConPaginacion')]
    public function test_limpiar_quita_todos_los_filtros(string $clase): void
    {
        $this->lista($clase)
            ->set('buscar', 'x')
            ->set('estado', 'anulada')
            ->set('almacen_id', 3)
            ->set('desde', '2026-01-01')
            ->set('hasta', '2026-12-31')
            ->call('limpiarFiltros')
            ->assertSet('buscar', '')
            ->assertSet('estado', '')
            ->assertSet('almacen_id', null)
            ->assertSet('desde', '')
            ->assertSet('hasta', '');
    }

    #[DataProvider('listasConPaginacion')]
    public function test_ignora_valores_de_filtro_no_permitidos(string $clase): void
    {
        $this->crear($clase, 2);

        $this->lista($clase)
            ->set('estado', 'cualquiera')
            ->set('desde', 'no-es-fecha')
            ->assertOk();
    }

    public function test_compras_filtra_por_texto_almacen_estado_y_fechas_en_pantalla(): void
    {
        $norte = Almacen::factory()->create(['nombre' => 'Almacén Norte']);
        Compra::factory()->create(['proveedor_id' => Proveedor::factory()->create(['nombre' => 'Agro Andina SAC'])->id, 'almacen_id' => $norte->id, 'fecha' => '2026-10-02']);
        Compra::factory()->create(['proveedor_id' => Proveedor::factory()->create(['nombre' => 'Semillas del Sur'])->id, 'fecha' => '2026-10-08', 'anulada_at' => now()]);

        $lista = $this->lista(CompraLista::class);

        $lista->set('buscar', 'andina')->assertSee('Agro Andina SAC')->assertDontSee('Semillas del Sur');
        $lista->set('buscar', '')->set('almacen_id', $norte->id)->assertSee('Agro Andina SAC')->assertDontSee('Semillas del Sur');
        $lista->set('almacen_id', null)->set('estado', 'anulada')->assertSee('Semillas del Sur')->assertDontSee('Agro Andina SAC');
        $lista->set('estado', '')->set('desde', '2026-10-05')->assertSee('Semillas del Sur')->assertDontSee('Agro Andina SAC');
        $lista->set('desde', '')->set('hasta', '2026-10-05')->assertSee('Agro Andina SAC')->assertDontSee('Semillas del Sur');
    }

    public function test_ventas_filtra_por_texto_en_pantalla_y_avisa_si_no_hay_resultados(): void
    {
        Venta::factory()->create(['cliente_nombre' => 'Ana Pérez']);
        Venta::factory()->create(['cliente_nombre' => 'Luis Gómez']);

        $this->lista(VentaLista::class)
            ->set('buscar', 'ana')
            ->assertSee('Ana Pérez')
            ->assertDontSee('Luis Gómez')
            ->assertSee('1 resultado(s)')
            ->set('buscar', 'zzz')
            ->assertSee('No se encontraron ventas con esos filtros');
    }

    public function test_los_historiales_vacios_siguen_avisando(): void
    {
        Almacen::query()->delete();

        $this->lista(CompraLista::class)->assertSee('No hay compras registradas');
        $this->lista(VentaLista::class)->assertSee('No hay ventas registradas');
        $this->lista(ProveedorLista::class)->assertSee('No hay proveedores registrados');
        $this->lista(AlmacenLista::class)->assertSee('No hay almacenes registrados');
    }

    public function test_proveedores_busca_filtra_por_estado_y_pagina(): void
    {
        Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789']);
        Proveedor::factory()->create(['nombre' => 'Semillas del Norte', 'activo' => false]);

        $lista = $this->lista(ProveedorLista::class);

        $lista->set('buscar', 'andina')->assertSee('Agro Andina SAC')->assertDontSee('Semillas del Norte');
        $lista->set('buscar', '')->set('estado', 'inactivo')->assertSee('Semillas del Norte')->assertDontSee('Agro Andina SAC');
        $lista->call('limpiarFiltros')->assertSee('Agro Andina SAC')->assertSee('Semillas del Norte');

        Proveedor::factory()->count(20)->create();
        $this->assertCount(15, $this->lista(ProveedorLista::class)->instance()->proveedores->items());
    }

    public function test_almacenes_busca_filtra_por_estado_y_sigue_mostrando_lotes(): void
    {
        Almacen::factory()->create(['nombre' => 'Central', 'encargado' => 'Rosa Quispe']);
        Almacen::factory()->create(['nombre' => 'Sur', 'activo' => false]);

        $lista = $this->lista(AlmacenLista::class);

        $lista->set('buscar', 'rosa')->assertSee('Central')->assertDontSee('Sur');
        $lista->set('buscar', '')->set('estado', 'inactivo')->assertSee('Sur')->assertDontSee('Central');
    }

    public function test_cambiar_el_estado_de_un_proveedor_sigue_funcionando_con_los_filtros(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC']);

        $this->lista(ProveedorLista::class)
            ->set('buscar', 'andina')
            ->call('cambiarEstado', $proveedor->id)
            ->assertSee('Desactivado');

        $this->assertFalse($proveedor->fresh()->activo);
    }
}
