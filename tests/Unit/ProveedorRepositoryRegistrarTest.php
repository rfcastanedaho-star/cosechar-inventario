<?php

namespace Tests\Unit;

use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedorRepositoryRegistrarTest extends TestCase
{
    use RefreshDatabase;

    private ProveedorRepository $repositorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = new ProveedorRepository;
    }

    public function test_crea_un_proveedor_nuevo_con_nombre_ruc_y_telefono(): void
    {
        $proveedor = $this->repositorio->registrarOReutilizar('Agro Andina SAC', '20123456789', '044-123456');

        $this->assertTrue($proveedor->wasRecentlyCreated);
        $this->assertSame('Agro Andina SAC', $proveedor->nombre);
        $this->assertSame('20123456789', $proveedor->ruc);
        $this->assertSame('044-123456', $proveedor->telefono);
        $this->assertTrue($proveedor->fresh()->activo);
    }

    public function test_el_ruc_y_el_telefono_son_opcionales(): void
    {
        $proveedor = $this->repositorio->registrarOReutilizar('Proveedor sin datos', null, null);

        $this->assertNull($proveedor->fresh()->ruc);
        $this->assertNull($proveedor->fresh()->telefono);
    }

    public function test_los_textos_vacios_se_guardan_como_nulos_y_se_recortan(): void
    {
        $proveedor = $this->repositorio->registrarOReutilizar('  Agro Andina  ', '  ', '');

        $this->assertSame('Agro Andina', $proveedor->nombre);
        $this->assertNull($proveedor->ruc);
        $this->assertNull($proveedor->telefono);
    }

    public function test_con_ruc_reutiliza_el_que_ya_existe_sin_pisar_sus_datos(): void
    {
        $existente = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789', 'telefono' => '111']);

        $proveedor = $this->repositorio->registrarOReutilizar('Otro nombre escrito', '20123456789', '999');

        $this->assertSame($existente->id, $proveedor->id);
        $this->assertSame(1, Proveedor::count());
        $this->assertSame('Agro Andina SAC', $existente->fresh()->nombre);
        $this->assertSame('111', $existente->fresh()->telefono);
    }

    public function test_sin_ruc_reutiliza_el_mismo_nombre_sin_importar_mayusculas(): void
    {
        $existente = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => null]);

        $proveedor = $this->repositorio->registrarOReutilizar('  AGRO andina sac ', null, null);

        $this->assertSame($existente->id, $proveedor->id);
        $this->assertSame(1, Proveedor::count());
    }

    public function test_con_ruc_nuevo_y_un_proveedor_del_mismo_nombre_sin_ruc_lo_completa_en_vez_de_duplicar(): void
    {
        $existente = Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => null, 'telefono' => null]);

        $proveedor = $this->repositorio->registrarOReutilizar('Agro Andina SAC', '20123456789', '044-123456');

        $this->assertSame($existente->id, $proveedor->id);
        $this->assertSame(1, Proveedor::count());
        $this->assertSame('20123456789', $existente->fresh()->ruc);
        $this->assertSame('044-123456', $existente->fresh()->telefono);
    }

    public function test_el_mismo_nombre_con_otro_ruc_es_otro_proveedor(): void
    {
        Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20111111111']);

        $nuevo = $this->repositorio->registrarOReutilizar('Agro Andina SAC', '20222222222', null);

        $this->assertTrue($nuevo->wasRecentlyCreated);
        $this->assertSame(2, Proveedor::count());
    }

    public function test_un_proveedor_desactivado_se_reutiliza_sin_reactivarlo(): void
    {
        $inactivo = Proveedor::factory()->create(['nombre' => 'Agro Retirado', 'ruc' => '20123456789', 'activo' => false]);

        $proveedor = $this->repositorio->registrarOReutilizar('Agro Retirado', '20123456789', null);

        $this->assertSame($inactivo->id, $proveedor->id);
        $this->assertFalse($inactivo->fresh()->activo);
    }

    public function test_sin_ruc_prefiere_el_proveedor_activo_si_hay_dos_con_el_mismo_nombre(): void
    {
        Proveedor::factory()->create(['nombre' => 'Agro Andina', 'ruc' => '20111111111', 'activo' => false]);
        $activo = Proveedor::factory()->create(['nombre' => 'Agro Andina', 'ruc' => '20222222222', 'activo' => true]);

        $this->assertSame($activo->id, $this->repositorio->registrarOReutilizar('Agro Andina', null, null)->id);
    }
}
