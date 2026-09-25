<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReporteExportacionTest extends TestCase
{
    use RefreshDatabase;

    public static function rutasDeExportacion(): array
    {
        return [
            ['reportes.stock.pdf'],
            ['reportes.stock.csv'],
            ['reportes.movimientos.pdf'],
            ['reportes.movimientos.csv'],
        ];
    }

    #[DataProvider('rutasDeExportacion')]
    public function test_el_operador_no_puede_exportar(string $ruta): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);

        $this->actingAs($operador)->get(route($ruta))->assertForbidden();
    }

    #[DataProvider('rutasDeExportacion')]
    public function test_el_administrador_si_puede_exportar(string $ruta): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($admin)->get(route($ruta))->assertOk();
    }

    public function test_el_operador_si_puede_ver_la_pagina_de_reportes(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);

        $this->actingAs($operador)->get(route('reportes'))->assertOk();
    }

    public function test_el_operador_no_ve_los_botones_de_exportar(): void
    {
        $operador = User::factory()->create(['rol' => 'operador_almacen']);

        $this->actingAs($operador)
            ->get(route('reportes'))
            ->assertDontSee('Excel (CSV)');
    }

    public function test_el_administrador_si_ve_los_botones_de_exportar(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($admin)
            ->get(route('reportes'))
            ->assertSee('Excel (CSV)');
    }
}
