<?php

namespace Tests\Unit;

use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\User;
use App\Repositories\StockMovimientoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovimientoRepositoryHistorialTest extends TestCase
{
    use RefreshDatabase;

    private StockMovimientoRepository $repositorio;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = new StockMovimientoRepository;
        $this->usuario = User::factory()->create();
    }

    private function movimiento(Lote $lote, string $tipo = 'entrada', int $cantidad = 5, string $fecha = '2026-10-10 09:00:00', ?string $motivo = null): StockMovimiento
    {
        return StockMovimiento::create([
            'lote_id' => $lote->id, 'tipo' => $tipo, 'cantidad' => $cantidad, 'fecha' => $fecha,
            'responsable_id' => $this->usuario->id, 'motivo' => $motivo,
        ]);
    }

    private function lote(string $producto = 'Fungicida Cobre', string $numero = 'L-001', ?Almacen $almacen = null): Lote
    {
        return Lote::factory()
            ->for(Producto::factory()->create(['nombre' => $producto, 'codigo' => 'C-'.$numero]), 'producto')
            ->create(['almacen_id' => ($almacen ?? Almacen::factory()->create())->id, 'numero_lote' => $numero]);
    }

    public function test_ordena_del_mas_reciente_al_mas_antiguo(): void
    {
        $lote = $this->lote();
        $this->movimiento($lote, fecha: '2026-10-01 08:00:00');
        $this->movimiento($lote, fecha: '2026-10-09 08:00:00');
        $this->movimiento($lote, fecha: '2026-10-05 08:00:00');

        $fechas = $this->repositorio->historial()->pluck('fecha')->map->toDateString()->all();

        $this->assertSame(['2026-10-09', '2026-10-05', '2026-10-01'], $fechas);
    }

    public function test_pagina_los_resultados(): void
    {
        $lote = $this->lote();
        foreach (range(1, 12) as $n) {
            $this->movimiento($lote);
        }

        $pagina = $this->repositorio->historial([], 5);

        $this->assertSame(12, $pagina->total());
        $this->assertCount(5, $pagina->items());
    }

    public function test_filtra_por_tipo(): void
    {
        $lote = $this->lote();
        $this->movimiento($lote, 'entrada');
        $this->movimiento($lote, 'salida');

        $this->assertSame(1, $this->repositorio->historial(['tipo' => 'salida'])->total());
        $this->assertSame(2, $this->repositorio->historial(['tipo' => ''])->total());
    }

    public function test_filtra_por_almacen(): void
    {
        $norte = Almacen::factory()->create();
        $sur = Almacen::factory()->create();
        $this->movimiento($this->lote('A', 'L-1', $norte));
        $this->movimiento($this->lote('B', 'L-2', $sur));

        $encontrados = $this->repositorio->historial(['almacen_id' => $sur->id]);

        $this->assertSame(1, $encontrados->total());
        $this->assertSame('L-2', $encontrados->items()[0]->lote->numero_lote);
    }

    public function test_filtra_por_rango_de_fechas_incluyendo_los_extremos(): void
    {
        $lote = $this->lote();
        $this->movimiento($lote, fecha: '2026-10-01 23:30:00');
        $this->movimiento($lote, fecha: '2026-10-05 10:00:00');
        $this->movimiento($lote, fecha: '2026-10-10 00:10:00');
        $this->movimiento($lote, fecha: '2026-10-11 09:00:00');

        $this->assertSame(3, $this->repositorio->historial(['desde' => '2026-10-01', 'hasta' => '2026-10-10'])->total());
        $this->assertSame(3, $this->repositorio->historial(['desde' => '2026-10-05'])->total());
        $this->assertSame(1, $this->repositorio->historial(['hasta' => '2026-10-01'])->total());
    }

    public function test_busca_por_nombre_codigo_o_numero_de_lote(): void
    {
        $this->movimiento($this->lote('Fungicida Cobre', 'LOTE-77'));
        $this->movimiento($this->lote('Herbicida Total', 'LOTE-88'));

        $this->assertSame(1, $this->repositorio->historial(['buscar' => 'fungi'])->total());
        $this->assertSame(1, $this->repositorio->historial(['buscar' => 'C-LOTE-88'])->total());
        $this->assertSame(1, $this->repositorio->historial(['buscar' => 'lote-77'])->total());
        $this->assertSame(0, $this->repositorio->historial(['buscar' => 'zzz'])->total());
    }

    public function test_la_busqueda_trata_los_comodines_como_texto(): void
    {
        $this->movimiento($this->lote('Fungicida Cobre', 'LOTE-77'));

        $this->assertSame(0, $this->repositorio->historial(['buscar' => '%%'])->total());
    }

    public function test_filtra_por_motivo_y_distingue_compras_sin_motivo_antiguas(): void
    {
        $lote = $this->lote();
        $this->movimiento($lote, 'entrada', motivo: 'compra');
        $this->movimiento($lote, 'salida', motivo: 'merma');
        $this->movimiento($lote, 'salida', motivo: 'merma');

        $this->assertSame(2, $this->repositorio->historial(['motivo' => 'merma'])->total());
        $this->assertSame(1, $this->repositorio->historial(['motivo' => 'compra'])->total());
    }

    public function test_combina_varios_filtros(): void
    {
        $lote = $this->lote('Fungicida Cobre', 'L-1');
        $this->movimiento($lote, 'entrada', fecha: '2026-10-02 09:00:00');
        $this->movimiento($lote, 'salida', fecha: '2026-10-03 09:00:00');
        $this->movimiento($this->lote('Otro', 'L-2'), 'salida', fecha: '2026-10-03 09:00:00');

        $total = $this->repositorio->historial(['buscar' => 'fungi', 'tipo' => 'salida', 'desde' => '2026-10-03'])->total();

        $this->assertSame(1, $total);
    }

    public function test_trae_producto_lote_almacen_y_responsable_sin_consultas_extra(): void
    {
        $lote = $this->lote();
        $this->movimiento($lote);
        $this->movimiento($lote);

        $primero = $this->repositorio->historial()->items()[0];

        $this->assertTrue($primero->relationLoaded('lote'));
        $this->assertTrue($primero->lote->relationLoaded('producto'));
        $this->assertTrue($primero->lote->relationLoaded('almacen'));
        $this->assertTrue($primero->relationLoaded('responsable'));
    }
}
