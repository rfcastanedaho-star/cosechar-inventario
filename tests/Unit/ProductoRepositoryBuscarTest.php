<?php

namespace Tests\Unit;

use App\Models\Producto;
use App\Repositories\ProductoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoRepositoryBuscarTest extends TestCase
{
    use RefreshDatabase;

    private ProductoRepository $repositorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = new ProductoRepository;
    }

    public function test_busca_por_las_iniciales_del_nombre(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'P-001']);
        Producto::factory()->create(['nombre' => 'Fertilizante Urea', 'codigo' => 'P-002']);
        Producto::factory()->create(['nombre' => 'Herbicida Total', 'codigo' => 'P-003']);

        $nombres = $this->repositorio->buscar('fu')->pluck('nombre')->all();

        $this->assertSame(['Fungicida Cobre'], $nombres);
    }

    public function test_busca_por_cualquier_parte_del_nombre_y_pone_primero_los_que_empiezan_igual(): void
    {
        Producto::factory()->create(['nombre' => 'Mancozeb Fungicida', 'codigo' => 'P-010']);
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'P-011']);

        $nombres = $this->repositorio->buscar('fung')->pluck('nombre')->all();

        $this->assertSame(['Fungicida Cobre', 'Mancozeb Fungicida'], $nombres);
    }

    public function test_busca_tambien_por_codigo(): void
    {
        Producto::factory()->create(['nombre' => 'Insecticida Alfa', 'codigo' => 'INS-2201']);
        Producto::factory()->create(['nombre' => 'Otro producto', 'codigo' => 'OTR-0001']);

        $this->assertSame(['INS-2201'], $this->repositorio->buscar('ins-22')->pluck('codigo')->all());
    }

    public function test_respeta_el_limite_de_resultados(): void
    {
        Producto::factory()->count(12)->sequence(fn ($s) => ['nombre' => 'Abono '.$s->index])->create();

        $this->assertCount(8, $this->repositorio->buscar('abono', 8));
        $this->assertCount(12, $this->repositorio->buscar('abono', 20));
    }

    public function test_un_termino_vacio_no_devuelve_nada(): void
    {
        Producto::factory()->count(3)->create();

        $this->assertCount(0, $this->repositorio->buscar(''));
        $this->assertCount(0, $this->repositorio->buscar('   '));
    }

    public function test_los_comodines_de_like_se_buscan_como_texto_literal(): void
    {
        Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'P-020']);
        Producto::factory()->create(['nombre' => 'Oferta 50% descuento', 'codigo' => 'P-021']);

        $this->assertCount(0, $this->repositorio->buscar('%%'));
        $this->assertSame(['P-021'], $this->repositorio->buscar('50%')->pluck('codigo')->all());
    }
}
