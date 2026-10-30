<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Con un solo almacén real, los almacenes vacíos que sobran se desactivan para que el sistema no pregunte cuál. */
class AlmacenesSobrantesMigracionTest extends TestCase
{
    use RefreshDatabase;

    private function ejecutar(): void
    {
        (require database_path('migrations/2026_10_30_140000_desactivar_almacenes_sobrantes_sin_stock.php'))->up();
    }

    public function test_desactiva_el_almacen_sobrante_que_no_tiene_stock(): void
    {
        $principal = Almacen::first();
        $sobrante = Almacen::factory()->create(['nombre' => 'Almacen Secundario']);

        $this->ejecutar();

        $this->assertTrue($principal->fresh()->activo);
        $this->assertFalse($sobrante->fresh()->activo);
        $this->assertSame($principal->id, Almacen::unicoActivo()?->id);
    }

    public function test_no_toca_los_almacenes_que_tienen_stock(): void
    {
        $principal = Almacen::first();
        $conStock = Almacen::factory()->create();
        foreach ([$principal, $conStock] as $almacen) {
            Lote::factory()->for(Producto::factory(), 'producto')->create(['almacen_id' => $almacen->id, 'cantidad' => 5]);
        }

        $this->ejecutar();

        $this->assertTrue($principal->fresh()->activo);
        $this->assertTrue($conStock->fresh()->activo);
    }

    public function test_si_hay_un_solo_almacen_no_cambia_nada(): void
    {
        $principal = Almacen::first();

        $this->ejecutar();

        $this->assertTrue($principal->fresh()->activo);
    }

    public function test_si_el_principal_esta_vacio_pero_otro_tiene_stock_conserva_al_que_tiene_stock(): void
    {
        $principal = Almacen::first();
        $conStock = Almacen::factory()->create();
        Lote::factory()->for(Producto::factory(), 'producto')->create(['almacen_id' => $conStock->id, 'cantidad' => 5]);

        $this->ejecutar();

        $this->assertFalse($principal->fresh()->activo);
        $this->assertTrue($conStock->fresh()->activo);
    }
}
