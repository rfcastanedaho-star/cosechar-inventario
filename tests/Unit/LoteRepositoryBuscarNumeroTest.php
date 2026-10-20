<?php

namespace Tests\Unit;

use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Repositories\LoteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoteRepositoryBuscarNumeroTest extends TestCase
{
    use RefreshDatabase;

    private LoteRepository $repositorio;

    private Almacen $almacen;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = new LoteRepository;
        $this->almacen = Almacen::factory()->create();
        $this->producto = Producto::factory()->create();
    }

    private function lote(string $numero, ?Almacen $almacen = null, ?Producto $producto = null): Lote
    {
        return Lote::factory()->for($producto ?? $this->producto, 'producto')->create([
            'almacen_id' => ($almacen ?? $this->almacen)->id,
            'numero_lote' => $numero,
        ]);
    }

    private function buscar(string $termino, int $limite = 8): array
    {
        return $this->repositorio
            ->buscarPorNumero($this->producto->id, $this->almacen->id, $termino, $limite)
            ->pluck('numero_lote')->all();
    }

    public function test_busca_por_cualquier_parte_del_numero_y_pone_primero_los_que_empiezan_igual(): void
    {
        $this->lote('B-2026-001');
        $this->lote('2026-001');
        $this->lote('Z-999');

        $this->assertSame(['2026-001', 'B-2026-001'], $this->buscar('2026'));
    }

    public function test_no_distingue_mayusculas_de_minusculas(): void
    {
        $this->lote('LOTE-ABC');

        $this->assertSame(['LOTE-ABC'], $this->buscar('lote-a'));
    }

    public function test_solo_devuelve_lotes_del_producto_y_almacen_indicados(): void
    {
        $this->lote('L-100');
        $this->lote('L-101', Almacen::factory()->create());
        $this->lote('L-102', null, Producto::factory()->create());

        $this->assertSame(['L-100'], $this->buscar('L-10'));
    }

    public function test_respeta_el_limite_de_resultados(): void
    {
        foreach (range(1, 12) as $n) {
            $this->lote('LT-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT));
        }

        $this->assertCount(8, $this->buscar('LT-', 8));
        $this->assertCount(12, $this->buscar('LT-', 20));
    }

    public function test_un_termino_vacio_no_devuelve_nada(): void
    {
        $this->lote('L-1');

        $this->assertSame([], $this->buscar(''));
        $this->assertSame([], $this->buscar('   '));
    }

    public function test_los_comodines_de_like_se_buscan_como_texto_literal(): void
    {
        $this->lote('L-1');
        $this->lote('L-50%');

        $this->assertSame([], $this->buscar('%%'));
        $this->assertSame(['L-50%'], $this->buscar('50%'));
    }
}
