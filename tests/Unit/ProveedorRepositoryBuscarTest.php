<?php

namespace Tests\Unit;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedorRepositoryBuscarTest extends TestCase
{
    use RefreshDatabase;

    private ProveedorRepository $repositorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = new ProveedorRepository;
    }

    private function nombres(string $termino, int $limite = 8, bool $soloActivos = true): array
    {
        return $this->repositorio->buscar($termino, $limite, $soloActivos)->pluck('nombre')->all();
    }

    public function test_al_escribir_una_inicial_van_primero_los_que_empiezan_con_ella(): void
    {
        Proveedor::factory()->create(['nombre' => 'Agroinsumos del Norte']);
        Proveedor::factory()->create(['nombre' => 'Rural Semillas']);
        Proveedor::factory()->create(['nombre' => 'Ricardo Fertilizantes']);
        Proveedor::factory()->create(['nombre' => 'Zeta Fuentes']);

        // Empiezan con R (en orden alfabético) y después el que solo contiene una r.
        $this->assertSame(['Ricardo Fertilizantes', 'Rural Semillas', 'Agroinsumos del Norte'], $this->nombres('r'));
    }

    public function test_con_mas_letras_se_acota_a_los_que_coinciden(): void
    {
        Proveedor::factory()->create(['nombre' => 'Agroinsumos del Norte']);
        Proveedor::factory()->create(['nombre' => 'Rural Semillas']);
        Proveedor::factory()->create(['nombre' => 'Ricardo Fertilizantes']);

        $this->assertSame(['Rural Semillas'], $this->nombres('ru'));
    }

    public function test_los_que_empiezan_igual_van_antes_que_los_que_solo_lo_contienen(): void
    {
        Proveedor::factory()->create(['nombre' => 'Distribuidora Rural']);
        Proveedor::factory()->create(['nombre' => 'Rural Semillas']);

        $this->assertSame(['Rural Semillas', 'Distribuidora Rural'], $this->nombres('rural'));
    }

    public function test_busca_tambien_por_ruc(): void
    {
        Proveedor::factory()->create(['nombre' => 'Agro Andina', 'ruc' => '20123456789']);
        Proveedor::factory()->create(['nombre' => 'Otro', 'ruc' => '20999888777']);

        $this->assertSame(['Agro Andina'], $this->nombres('2012345'));
    }

    public function test_no_distingue_mayusculas_de_minusculas(): void
    {
        Proveedor::factory()->create(['nombre' => 'Agro Andina']);

        $this->assertSame(['Agro Andina'], $this->nombres('AGRO'));
    }

    public function test_por_defecto_ignora_los_proveedores_desactivados(): void
    {
        Proveedor::factory()->create(['nombre' => 'Rural Activo']);
        Proveedor::factory()->create(['nombre' => 'Rural Retirado', 'activo' => false]);

        $this->assertSame(['Rural Activo'], $this->nombres('rural'));
        $this->assertEqualsCanonicalizing(['Rural Activo', 'Rural Retirado'], $this->nombres('rural', 8, false));
    }

    public function test_respeta_el_limite_de_resultados(): void
    {
        foreach (range(1, 12) as $n) {
            Proveedor::factory()->create(['nombre' => 'Rural '.$n]);
        }

        $this->assertCount(8, $this->nombres('rural', 8));
        $this->assertCount(12, $this->nombres('rural', 20));
    }

    public function test_un_termino_vacio_no_devuelve_nada(): void
    {
        Proveedor::factory()->create();

        $this->assertSame([], $this->nombres(''));
        $this->assertSame([], $this->nombres('   '));
    }

    public function test_los_comodines_de_like_se_buscan_como_texto_literal(): void
    {
        Proveedor::factory()->create(['nombre' => 'Rural Semillas']);
        Proveedor::factory()->create(['nombre' => 'Oferta 50% Agro']);

        $this->assertSame([], $this->nombres('%%'));
        $this->assertSame(['Oferta 50% Agro'], $this->nombres('50%'));
    }
}
