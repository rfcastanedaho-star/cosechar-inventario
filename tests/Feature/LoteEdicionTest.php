<?php

namespace Tests\Feature;

use App\Livewire\LoteLista;
use App\Models\AuditoriaCambio;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Support\AuditoriaEtiquetas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los lotes que nacen en Compras salen sin vencimiento: aquí se completa el N.º de lote y el
 * vencimiento. Se corrige solo eso (la cantidad se ajusta con +/−) y queda en el historial de cambios.
 */
class LoteEdicionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create(['rol' => 'operador_almacen']);
    }

    private function lote(array $datos = [], bool $usaLotes = true): Lote
    {
        return Lote::factory()
            ->for(Producto::factory()->create(['maneja_vencimiento' => $usaLotes]), 'producto')
            ->create(['numero_lote' => 'C7-1', 'fecha_vencimiento' => null, 'cantidad' => 25, ...$datos]);
    }

    private function pantalla(): Testable
    {
        return Livewire::actingAs($this->usuario)->test(LoteLista::class);
    }

    public function test_cada_lote_ofrece_editar_menos_el_automatico_sin_lote(): void
    {
        $this->lote();
        $this->lote(['numero_lote' => Lote::SIN_LOTE], usaLotes: false);

        $html = $this->pantalla()->html();

        $this->assertSame(1, substr_count($html, 'Editar lote C7-1'));
        $this->assertStringNotContainsString('Editar lote Sin lote', $html);
    }

    public function test_el_lote_pendiente_de_vencimiento_se_avisa(): void
    {
        $this->lote();

        $this->pantalla()->assertSee('Falta completar');
    }

    public function test_un_producto_sin_lotes_no_avisa_falta_de_vencimiento(): void
    {
        $this->lote(['numero_lote' => 'X-1'], usaLotes: false);

        $this->pantalla()->assertDontSee('Falta completar')->assertSee('No aplica');
    }

    public function test_al_iniciar_la_edicion_se_cargan_el_numero_y_el_vencimiento(): void
    {
        $lote = $this->lote(['fecha_vencimiento' => '2027-02-01']);

        $this->pantalla()
            ->call('iniciarEdicion', $lote->id)
            ->assertSet('editandoId', $lote->id)
            ->assertSet('editNumero', 'C7-1')
            ->assertSet('editVencimiento', '2027-02-01');
    }

    public function test_el_lote_automatico_no_se_edita(): void
    {
        $lote = $this->lote(['numero_lote' => Lote::SIN_LOTE], usaLotes: false);

        $this->pantalla()->call('iniciarEdicion', $lote->id)->assertSet('editandoId', null);
    }

    public function test_guardar_cambia_numero_y_vencimiento_sin_tocar_el_stock(): void
    {
        $lote = $this->lote();

        $this->pantalla()
            ->call('iniciarEdicion', $lote->id)
            ->set('editNumero', 'L-2026-77')
            ->set('editVencimiento', '2027-05-20')
            ->call('guardarEdicion')
            ->assertHasNoErrors()
            ->assertSet('editandoId', null)
            ->assertSee('L-2026-77');

        $lote->refresh();
        $this->assertSame('L-2026-77', $lote->numero_lote);
        $this->assertSame('2027-05-20', $lote->fecha_vencimiento->toDateString());
        $this->assertSame(25, $lote->cantidad);
        $this->assertSame(0, StockMovimiento::count());
    }

    public function test_cada_cambio_queda_en_el_historial_con_quien_lo_hizo(): void
    {
        $lote = $this->lote();

        $this->pantalla()
            ->call('iniciarEdicion', $lote->id)
            ->set('editNumero', 'L-NUEVO')
            ->set('editVencimiento', '2027-05-20')
            ->call('guardarEdicion');

        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Lote::class, 'auditable_id' => $lote->id, 'user_id' => $this->usuario->id,
            'campo' => 'numero_lote', 'valor_anterior' => 'C7-1', 'valor_nuevo' => 'L-NUEVO',
        ]);
        $this->assertSame(1, AuditoriaCambio::where('auditable_id', $lote->id)->where('campo', 'fecha_vencimiento')->count());
    }

    public function test_si_no_cambia_nada_no_se_escribe_historial(): void
    {
        $lote = $this->lote(['fecha_vencimiento' => '2027-02-01']);

        $this->pantalla()->call('iniciarEdicion', $lote->id)->call('guardarEdicion')->assertHasNoErrors();

        $this->assertSame(0, AuditoriaCambio::count());
    }

    public function test_el_vencimiento_se_puede_dejar_vacio(): void
    {
        $lote = $this->lote(['fecha_vencimiento' => '2027-02-01']);

        $this->pantalla()->call('iniciarEdicion', $lote->id)->set('editVencimiento', '')->call('guardarEdicion')->assertHasNoErrors();

        $this->assertNull($lote->fresh()->fecha_vencimiento);
    }

    public function test_el_numero_es_obligatorio_y_el_vencimiento_debe_ser_fecha(): void
    {
        $lote = $this->lote();

        $this->pantalla()
            ->call('iniciarEdicion', $lote->id)
            ->set('editNumero', '')
            ->set('editVencimiento', 'pronto')
            ->call('guardarEdicion')
            ->assertHasErrors(['editNumero' => 'required', 'editVencimiento' => 'date'])
            ->assertSet('editandoId', $lote->id);
    }

    public function test_no_se_repite_el_numero_dentro_del_mismo_producto(): void
    {
        $lote = $this->lote();
        Lote::factory()->for($lote->producto, 'producto')->create(['numero_lote' => 'L-OCUPADO']);

        $this->pantalla()
            ->call('iniciarEdicion', $lote->id)
            ->set('editNumero', 'L-OCUPADO')
            ->call('guardarEdicion')
            ->assertHasErrors(['editNumero' => 'unique']);
    }

    public function test_dos_productos_distintos_pueden_usar_el_mismo_numero(): void
    {
        $lote = $this->lote();
        $otro = $this->lote(['numero_lote' => 'L-COMUN']);

        $this->pantalla()
            ->call('iniciarEdicion', $lote->id)
            ->set('editNumero', 'L-COMUN')
            ->call('guardarEdicion')
            ->assertHasNoErrors();

        $this->assertSame('L-COMUN', $lote->fresh()->numero_lote);
        $this->assertNotSame($lote->producto_id, $otro->producto_id);
    }

    public function test_el_nombre_del_lote_automatico_esta_reservado(): void
    {
        $lote = $this->lote();

        $this->pantalla()
            ->call('iniciarEdicion', $lote->id)
            ->set('editNumero', Lote::SIN_LOTE)
            ->call('guardarEdicion')
            ->assertHasErrors(['editNumero' => 'not_in']);
    }

    public function test_cancelar_descarta_lo_escrito(): void
    {
        $lote = $this->lote();

        $this->pantalla()
            ->call('iniciarEdicion', $lote->id)
            ->set('editNumero', 'OTRO')
            ->call('cancelarEdicion')
            ->assertSet('editandoId', null)
            ->assertSet('editNumero', '');

        $this->assertSame('C7-1', $lote->fresh()->numero_lote);
    }

    public function test_completar_el_vencimiento_cambia_el_estado_del_lote(): void
    {
        $lote = $this->lote();
        $this->assertSame('vigente', $lote->estado);

        $this->pantalla()->call('iniciarEdicion', $lote->id)->set('editVencimiento', now()->subDay()->toDateString())->call('guardarEdicion');

        $this->assertSame('vencido', $lote->fresh()->estado);
    }

    public function test_el_historial_de_cambios_nombra_los_campos_del_lote(): void
    {
        $this->assertSame('N.º de lote', AuditoriaEtiquetas::campo('numero_lote'));
        $this->assertSame('Vencimiento', AuditoriaEtiquetas::campo('fecha_vencimiento'));
        $this->assertSame('Lote', AuditoriaEtiquetas::entidad(Lote::class));
        $this->assertSame('20/05/2027', AuditoriaEtiquetas::valor('fecha_vencimiento', '2027-05-20 00:00:00'));
        $this->assertSame('Fungicida Cobre — C7-1', AuditoriaEtiquetas::registro($this->lote()->setRelation('producto', Producto::factory()->make(['nombre' => 'Fungicida Cobre']))));
    }
}
