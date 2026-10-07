<?php

namespace Tests\Feature;

use App\Livewire\MovimientoForm;
use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los QR por lote se eliminaron porque su página pública mostraba stock,
 * almacén y fechas internas a cualquiera que escaneara la etiqueta.
 */
class SinCodigosQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_publica_del_lote_ya_no_existe(): void
    {
        $lote = Lote::factory()->for(Producto::factory(), 'producto')->create(['almacen_id' => Almacen::factory()]);

        $this->get("/lotes/{$lote->id}/consulta")->assertNotFound();
    }

    public function test_la_etiqueta_qr_ya_no_existe_ni_para_usuarios_con_sesion(): void
    {
        $usuario = User::factory()->create(['rol' => 'administrador']);
        $lote = Lote::factory()->for(Producto::factory(), 'producto')->create(['almacen_id' => Almacen::factory()]);

        $this->actingAs($usuario)->get("/lotes/{$lote->id}/etiqueta")->assertNotFound();
    }

    public function test_no_quedan_rutas_de_qr_registradas(): void
    {
        $this->assertFalse(Route::has('lotes.consulta'));
        $this->assertFalse(Route::has('lotes.etiqueta'));
    }

    public function test_los_lotes_ya_no_guardan_una_url_de_qr(): void
    {
        $this->assertFalse(Schema::hasColumn('lotes', 'codigo_qr'));
    }

    public function test_registrar_una_entrada_ya_no_ofrece_la_etiqueta_qr(): void
    {
        $usuario = User::factory()->create();
        $almacen = Almacen::factory()->create();
        $producto = Producto::factory()->create(['maneja_vencimiento' => false]);

        Livewire::actingAs($usuario)
            ->test(MovimientoForm::class)
            ->set('tipo', 'entrada')
            ->set('producto_id', $producto->id)
            ->set('almacen_id', $almacen->id)
            ->set('numero_lote', 'L-SIN-QR')
            ->set('cantidad', 5)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDontSee('etiqueta QR');
    }
}
