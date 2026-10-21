<?php

namespace Tests\Unit;

use App\Models\Almacen;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Repositories\AlmacenRepository;
use App\Repositories\CompraRepository;
use App\Repositories\ProveedorRepository;
use App\Repositories\VentaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorialesRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function compra(array $atributos = [], ?Producto $producto = null): Compra
    {
        $compra = Compra::factory()->create($atributos);

        if ($producto) {
            $lote = Lote::factory()->for($producto, 'producto')->create(['almacen_id' => $compra->almacen_id]);
            CompraDetalle::create(['compra_id' => $compra->id, 'lote_id' => $lote->id, 'cantidad' => 5, 'costo_unitario' => 3]);
        }

        return $compra;
    }

    private function venta(array $atributos = [], ?Producto $producto = null): Venta
    {
        $venta = Venta::factory()->create($atributos);

        if ($producto) {
            VentaDetalle::create(['venta_id' => $venta->id, 'producto_id' => $producto->id, 'cantidad' => 2, 'precio_unitario' => 10]);
        }

        return $venta;
    }

    // ---------- Compras ----------

    public function test_compras_van_de_la_mas_reciente_a_la_mas_antigua_y_se_paginan(): void
    {
        $this->compra(['fecha' => '2026-10-01']);
        $this->compra(['fecha' => '2026-10-09']);
        $this->compra(['fecha' => '2026-10-05']);

        $pagina = (new CompraRepository)->historial([], 2);

        $this->assertSame(3, $pagina->total());
        $this->assertSame(['2026-10-09', '2026-10-05'], collect($pagina->items())->map(fn ($c) => $c->fecha->toDateString())->all());
    }

    public function test_compras_se_buscan_por_proveedor_comprobante_o_producto(): void
    {
        $this->compra(['proveedor_id' => Proveedor::factory()->create(['nombre' => 'Agro Andina SAC'])->id, 'numero_comprobante' => 'F001-0001']);
        $this->compra(['proveedor_id' => Proveedor::factory()->create(['nombre' => 'Semillas del Norte'])->id, 'numero_comprobante' => 'F001-0002'], Producto::factory()->create(['nombre' => 'Fungicida Cobre', 'codigo' => 'FUN-9']));

        $repo = new CompraRepository;

        $this->assertSame(1, $repo->historial(['buscar' => 'andina'])->total());
        $this->assertSame(1, $repo->historial(['buscar' => 'F001-0002'])->total());
        $this->assertSame(1, $repo->historial(['buscar' => 'fungicida'])->total());
        $this->assertSame(1, $repo->historial(['buscar' => 'FUN-9'])->total());
        $this->assertSame(2, $repo->historial(['buscar' => 'F001'])->total());
        $this->assertSame(0, $repo->historial(['buscar' => 'zzz'])->total());
        $this->assertSame(0, $repo->historial(['buscar' => '%%'])->total());
    }

    public function test_compras_se_filtran_por_almacen_estado_y_fechas(): void
    {
        $norte = Almacen::factory()->create();
        $this->compra(['almacen_id' => $norte->id, 'fecha' => '2026-10-02']);
        $this->compra(['fecha' => '2026-10-08', 'anulada_at' => now()]);

        $repo = new CompraRepository;

        $this->assertSame(1, $repo->historial(['almacen_id' => $norte->id])->total());
        $this->assertSame(1, $repo->historial(['estado' => 'anulada'])->total());
        $this->assertSame(1, $repo->historial(['estado' => 'vigente'])->total());
        $this->assertSame(2, $repo->historial(['estado' => ''])->total());
        $this->assertSame(1, $repo->historial(['desde' => '2026-10-05'])->total());
        $this->assertSame(1, $repo->historial(['hasta' => '2026-10-05'])->total());
        $this->assertSame(2, $repo->historial(['desde' => '2026-10-02', 'hasta' => '2026-10-08'])->total());
        $this->assertSame(2, $repo->historial(['desde' => 'no-es-fecha'])->total());
    }

    public function test_compras_traen_sus_relaciones_para_la_tabla(): void
    {
        $this->compra([], Producto::factory()->create());

        $compra = (new CompraRepository)->historial()->items()[0];

        $this->assertTrue($compra->relationLoaded('proveedor'));
        $this->assertTrue($compra->relationLoaded('almacen'));
        $this->assertTrue($compra->relationLoaded('detalles'));
        $this->assertTrue($compra->relationLoaded('anuladaPor'));
    }

    // ---------- Ventas ----------

    public function test_ventas_van_de_la_mas_reciente_a_la_mas_antigua_y_se_paginan(): void
    {
        $this->venta(['fecha' => '2026-10-01']);
        $this->venta(['fecha' => '2026-10-09']);
        $this->venta(['fecha' => '2026-10-05']);

        $pagina = (new VentaRepository)->historial([], 2);

        $this->assertSame(3, $pagina->total());
        $this->assertSame(['2026-10-09', '2026-10-05'], collect($pagina->items())->map(fn ($v) => $v->fecha->toDateString())->all());
    }

    public function test_ventas_se_buscan_por_cliente_documento_comprobante_o_producto(): void
    {
        $this->venta(['cliente_nombre' => 'Ana Pérez', 'cliente_documento' => '12345678', 'numero_comprobante' => 'B001-0001']);
        $this->venta(['cliente_nombre' => 'Luis Gómez', 'cliente_documento' => '87654321', 'numero_comprobante' => 'B001-0002'], Producto::factory()->create(['nombre' => 'Herbicida Total', 'codigo' => 'HER-7']));

        $repo = new VentaRepository;

        $this->assertSame(1, $repo->historial(['buscar' => 'ana'])->total());
        $this->assertSame(1, $repo->historial(['buscar' => '8765'])->total());
        $this->assertSame(1, $repo->historial(['buscar' => 'B001-0002'])->total());
        $this->assertSame(1, $repo->historial(['buscar' => 'herbicida'])->total());
        $this->assertSame(1, $repo->historial(['buscar' => 'HER-7'])->total());
        $this->assertSame(2, $repo->historial(['buscar' => 'B001'])->total());
        $this->assertSame(0, $repo->historial(['buscar' => '%%'])->total());
    }

    public function test_ventas_se_filtran_por_almacen_estado_y_fechas(): void
    {
        $sur = Almacen::factory()->create();
        $this->venta(['almacen_id' => $sur->id, 'fecha' => '2026-10-02']);
        $this->venta(['fecha' => '2026-10-08', 'anulada_at' => now()]);

        $repo = new VentaRepository;

        $this->assertSame(1, $repo->historial(['almacen_id' => $sur->id])->total());
        $this->assertSame(1, $repo->historial(['estado' => 'anulada'])->total());
        $this->assertSame(1, $repo->historial(['estado' => 'vigente'])->total());
        $this->assertSame(1, $repo->historial(['desde' => '2026-10-05'])->total());
        $this->assertSame(1, $repo->historial(['hasta' => '2026-10-05'])->total());
    }

    public function test_ventas_traen_sus_relaciones_para_la_tabla(): void
    {
        $this->venta([], Producto::factory()->create());

        $venta = (new VentaRepository)->historial()->items()[0];

        $this->assertTrue($venta->relationLoaded('almacen'));
        $this->assertTrue($venta->relationLoaded('detalles'));
        $this->assertTrue($venta->relationLoaded('anuladaPor'));
    }

    // ---------- Proveedores y almacenes ----------

    public function test_proveedores_se_buscan_por_nombre_o_ruc_y_se_filtran_por_estado(): void
    {
        Proveedor::factory()->create(['nombre' => 'Agro Andina SAC', 'ruc' => '20123456789']);
        Proveedor::factory()->create(['nombre' => 'Semillas del Norte', 'ruc' => '20999888777', 'activo' => false]);

        $repo = new ProveedorRepository;

        $this->assertSame(['Agro Andina SAC'], $repo->historial(['buscar' => 'andina'])->pluck('nombre')->all());
        $this->assertSame(['Semillas del Norte'], $repo->historial(['buscar' => '999888'])->pluck('nombre')->all());
        $this->assertSame(['Semillas del Norte'], $repo->historial(['estado' => 'inactivo'])->pluck('nombre')->all());
        $this->assertSame(['Agro Andina SAC'], $repo->historial(['estado' => 'activo'])->pluck('nombre')->all());
        $this->assertSame(2, $repo->historial([])->total());
        $this->assertSame(0, $repo->historial(['buscar' => '%%'])->total());
    }

    public function test_proveedores_van_en_orden_alfabetico_y_se_paginan(): void
    {
        foreach (['Zeta', 'Alfa', 'Medio'] as $nombre) {
            Proveedor::factory()->create(['nombre' => $nombre]);
        }

        $pagina = (new ProveedorRepository)->historial([], 2);

        $this->assertSame(3, $pagina->total());
        $this->assertSame(['Alfa', 'Medio'], collect($pagina->items())->pluck('nombre')->all());
    }

    public function test_almacenes_se_buscan_por_nombre_direccion_o_encargado_y_se_filtran_por_estado(): void
    {
        Almacen::factory()->create(['nombre' => 'Central', 'direccion' => 'Av. Perú 100', 'encargado' => 'Rosa Quispe']);
        Almacen::factory()->create(['nombre' => 'Sur', 'direccion' => 'Jr. Lima 5', 'encargado' => 'Luis Gómez', 'activo' => false]);

        $repo = new AlmacenRepository;

        $this->assertSame(['Central'], $repo->historial(['buscar' => 'central'])->pluck('nombre')->all());
        $this->assertSame(['Sur'], $repo->historial(['buscar' => 'lima'])->pluck('nombre')->all());
        $this->assertSame(['Central'], $repo->historial(['buscar' => 'rosa'])->pluck('nombre')->all());
        $this->assertSame(['Sur'], $repo->historial(['estado' => 'inactivo'])->pluck('nombre')->all());
        $this->assertContains('Central', $repo->historial(['estado' => 'activo'])->pluck('nombre')->all());
        $this->assertNotContains('Sur', $repo->historial(['estado' => 'activo'])->pluck('nombre')->all());
    }

    public function test_almacenes_incluyen_la_cantidad_de_lotes(): void
    {
        $almacen = Almacen::factory()->create();
        Lote::factory()->count(2)->create(['almacen_id' => $almacen->id]);

        $fila = collect((new AlmacenRepository)->historial([])->items())->firstWhere('id', $almacen->id);

        $this->assertSame(2, $fila->lotes_count);
    }
}
