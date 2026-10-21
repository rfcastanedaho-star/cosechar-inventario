<?php

namespace Tests\Unit;

use App\Models\Cliente;
use App\Repositories\ClienteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ClienteRepository $repositorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = new ClienteRepository;
    }

    private function nombres(string $termino, int $limite = 8, bool $soloActivos = true): array
    {
        return $this->repositorio->buscar($termino, $limite, $soloActivos)->pluck('nombre')->all();
    }

    public function test_busca_por_las_iniciales_del_nombre_con_los_que_empiezan_igual_primero(): void
    {
        Cliente::factory()->create(['nombre' => 'Distribuidora Rural']);
        Cliente::factory()->create(['nombre' => 'Rural Semillas']);
        Cliente::factory()->create(['nombre' => 'Zeta Fuentes']);

        $this->assertSame(['Rural Semillas', 'Distribuidora Rural'], $this->nombres('rural'));
        $this->assertSame(['Rural Semillas'], $this->nombres('rural s'));
    }

    public function test_busca_por_documento_y_telefono(): void
    {
        Cliente::factory()->create(['nombre' => 'Ana Pérez', 'documento' => '12345678', 'telefono' => '987654321']);
        Cliente::factory()->create(['nombre' => 'Luis Gómez', 'documento' => '87654321', 'telefono' => '911222333']);

        $this->assertSame(['Ana Pérez'], $this->nombres('1234'));
        $this->assertSame(['Luis Gómez'], $this->nombres('9112'));
    }

    public function test_por_defecto_ignora_los_clientes_desactivados(): void
    {
        Cliente::factory()->create(['nombre' => 'Rural Activo']);
        Cliente::factory()->create(['nombre' => 'Rural Retirado', 'activo' => false]);

        $this->assertSame(['Rural Activo'], $this->nombres('rural'));
        $this->assertCount(2, $this->nombres('rural', 8, false));
    }

    public function test_respeta_el_limite_y_un_termino_vacio_no_devuelve_nada(): void
    {
        foreach (range(1, 12) as $n) {
            Cliente::factory()->create(['nombre' => 'Rural '.$n]);
        }

        $this->assertCount(8, $this->nombres('rural', 8));
        $this->assertSame([], $this->nombres(''));
        $this->assertSame([], $this->nombres('   '));
    }

    public function test_los_comodines_de_like_se_buscan_como_texto(): void
    {
        Cliente::factory()->create(['nombre' => 'Rural Semillas']);
        Cliente::factory()->create(['nombre' => 'Oferta 50% Agro']);

        $this->assertSame([], $this->nombres('%%'));
        $this->assertSame(['Oferta 50% Agro'], $this->nombres('50%'));
    }

    public function test_el_listado_se_busca_filtra_por_estado_y_pagina_en_orden_alfabetico(): void
    {
        Cliente::factory()->create(['nombre' => 'Zeta', 'documento' => '11111111']);
        Cliente::factory()->create(['nombre' => 'Alfa', 'documento' => '22222222', 'activo' => false]);
        Cliente::factory()->create(['nombre' => 'Medio', 'documento' => '33333333']);

        $this->assertSame(['Alfa', 'Medio'], collect($this->repositorio->historial([], 2)->items())->pluck('nombre')->all());
        $this->assertSame(['Alfa'], $this->repositorio->historial(['estado' => 'inactivo'])->pluck('nombre')->all());
        $this->assertSame(['Zeta', 'Medio'], $this->repositorio->historial(['estado' => 'activo'])->pluck('nombre')->sortDesc()->values()->all());
        $this->assertSame(['Medio'], $this->repositorio->historial(['buscar' => '3333'])->pluck('nombre')->all());
        $this->assertSame(0, $this->repositorio->historial(['buscar' => '%%'])->total());
    }
}
