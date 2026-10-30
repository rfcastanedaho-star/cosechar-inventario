<?php

namespace Tests\Feature;

use App\Livewire\CompraForm;
use App\Livewire\ProveedorForm;
use App\Livewire\ProveedorLista;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Repositories\ProveedorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CompraRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    private function producto(array $datos = []): Producto
    {
        return Producto::factory()->create(['maneja_vencimiento' => false, ...$datos]);
    }

    /** Formulario con una línea completa: 4 unidades a S/ 5.50 = S/ 22.00. */
    private function compra(?Producto $producto = null): Testable
    {
        $producto ??= $this->producto(['nombre' => 'Fungicida Cobre']);

        return Livewire::actingAs($this->admin())
            ->test(CompraForm::class)
            ->set('proveedor_nombre', 'Agro Andina SAC')
            ->call('elegirProductoLinea', 0, $producto->id)
            ->set('lineas.0.cantidad', 4)
            ->set('lineas.0.costo_unitario', '5.50')
            ->set('lineas.0.numero_lote', 'L-001');
    }

    // ---------- Campos del proveedor y de la factura ----------

    public function test_el_formulario_sigue_el_orden_pedido(): void
    {
        $texto = preg_replace('/\s+/u', ' ', strip_tags(Livewire::actingAs($this->admin())->test(CompraForm::class)->html()));

        $this->assertStringContainsString('Registrar compra', $texto);

        $orden = ['Nombre del proveedor *', 'Teléfono', 'Correo', 'Dirección', 'N.º de factura', 'Fecha de factura *', 'Productos comprados', 'Producto *', 'Cantidad *', 'Costo unitario (S/) *', 'N.º de lote *', 'Vencimiento (si aplica)'];
        $posicion = -1;

        foreach ($orden as $etiqueta) {
            $encontrada = strpos($texto, $etiqueta, $posicion + 1);
            $this->assertNotFalse($encontrada, "Falta o está fuera de orden: {$etiqueta}");
            $posicion = $encontrada;
        }
    }

    public function test_ya_no_se_habla_de_comprobante_ni_de_fecha_a_secas(): void
    {
        $texto = preg_replace('/\s+/u', ' ', strip_tags(Livewire::actingAs($this->admin())->test(CompraForm::class)->html()));

        $this->assertStringNotContainsString('N.º de comprobante', $texto);
        $this->assertStringNotContainsString('Fecha *', $texto);
    }

    public function test_la_tabla_de_proveedores_tiene_correo(): void
    {
        $this->assertTrue(Schema::hasColumn('proveedores', 'correo'));
    }

    public function test_el_correo_y_la_direccion_se_guardan_con_el_proveedor_nuevo(): void
    {
        $this->compra()
            ->set('proveedor_correo', 'ventas@agroandina.pe')
            ->set('proveedor_direccion', 'Av. Larco 123, Trujillo')
            ->call('guardar')
            ->assertHasNoErrors();

        $proveedor = Proveedor::where('nombre', 'Agro Andina SAC')->firstOrFail();
        $this->assertSame('ventas@agroandina.pe', $proveedor->correo);
        $this->assertSame('Av. Larco 123, Trujillo', $proveedor->direccion);
    }

    public function test_el_correo_debe_ser_valido_y_los_dos_campos_son_opcionales(): void
    {
        $this->compra()->set('proveedor_correo', 'no-es-correo')->call('guardar')->assertHasErrors(['proveedor_correo' => 'email']);
        $this->assertDatabaseCount('compras', 0);

        $this->compra()->call('guardar')->assertHasNoErrors();
        $this->assertNull(Proveedor::firstOrFail()->correo);
    }

    public function test_al_elegir_un_proveedor_registrado_se_rellenan_correo_y_direccion(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Rural Semillas', 'correo' => 'rural@semillas.pe', 'direccion' => 'Jr. Lima 45']);

        Livewire::actingAs($this->admin())
            ->test(CompraForm::class)
            ->call('elegirProveedor', $proveedor->id)
            ->assertSet('proveedor_correo', 'rural@semillas.pe')
            ->assertSet('proveedor_direccion', 'Jr. Lima 45');
    }

    public function test_el_repositorio_completa_correo_y_direccion_solo_si_estaban_vacios(): void
    {
        $repositorio = new ProveedorRepository;

        $nuevo = $repositorio->registrarOReutilizar('Agro Sur', null, null, 'a@sur.pe', 'Calle 1');
        $this->assertSame('a@sur.pe', $nuevo->correo);
        $this->assertSame('Calle 1', $nuevo->direccion);

        $mismo = $repositorio->registrarOReutilizar('agro sur', null, null, 'otro@sur.pe', 'Otra calle');
        $this->assertSame($nuevo->id, $mismo->id);
        $this->assertSame('a@sur.pe', $mismo->fresh()->correo);
        $this->assertSame('Calle 1', $mismo->fresh()->direccion);
    }

    public function test_el_proveedor_se_edita_con_correo_desde_su_pantalla(): void
    {
        $proveedor = Proveedor::factory()->create(['correo' => null]);

        Livewire::actingAs($this->admin())
            ->test(ProveedorForm::class, ['proveedor' => $proveedor])
            ->assertSee('Correo')
            ->set('correo', 'nuevo@proveedor.pe')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('nuevo@proveedor.pe', $proveedor->fresh()->correo);

        Livewire::actingAs($this->admin())
            ->test(ProveedorForm::class, ['proveedor' => $proveedor])
            ->set('correo', 'malo')
            ->call('guardar')
            ->assertHasErrors(['correo' => 'email']);
    }

    public function test_el_listado_de_proveedores_muestra_el_correo(): void
    {
        Proveedor::factory()->create(['correo' => 'contacto@agro.pe']);

        Livewire::actingAs($this->admin())->test(ProveedorLista::class)->assertSee('Correo')->assertSee('contacto@agro.pe');
    }

    // ---------- Paso de verificación ----------

    public function test_revisar_muestra_el_total_y_no_guarda_nada(): void
    {
        $this->compra()
            ->assertSet('confirmando', false)
            ->call('revisar')
            ->assertHasNoErrors()
            ->assertSet('confirmando', true)
            ->assertSee('Verifique la compra')
            ->assertSee('Fungicida Cobre')
            ->assertSee('L-001')
            ->assertSee('S/ 22.00')
            ->assertSee('Agregar compra')
            ->assertSee('Volver a editar');

        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('lotes', 0);
        $this->assertDatabaseCount('proveedores', 0);
    }

    public function test_la_revision_suma_varias_lineas(): void
    {
        $otro = $this->producto(['nombre' => 'Urea Agricola']);

        $this->compra()
            ->call('agregarLinea')
            ->call('elegirProductoLinea', 1, $otro->id)
            ->set('lineas.1.cantidad', 10)
            ->set('lineas.1.costo_unitario', '3')
            ->set('lineas.1.numero_lote', 'L-002')
            ->call('revisar')
            ->assertSee('Urea Agricola')
            ->assertSee('S/ 52.00');
    }

    public function test_la_revision_muestra_los_datos_del_proveedor_y_la_factura(): void
    {
        $this->compra()
            ->set('proveedor_telefono', '987654321')
            ->set('proveedor_correo', 'ventas@agroandina.pe')
            ->set('proveedor_direccion', 'Av. Larco 123')
            ->set('numero_comprobante', 'F001-000123')
            ->set('fecha', '2026-10-05')
            ->call('revisar')
            ->assertSee('Agro Andina SAC')
            ->assertSee('987654321')
            ->assertSee('ventas@agroandina.pe')
            ->assertSee('Av. Larco 123')
            ->assertSee('F001-000123')
            ->assertSee('05/10/2026');
    }

    public function test_revisar_con_datos_incompletos_no_pasa_al_resumen(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CompraForm::class)
            ->call('revisar')
            ->assertSet('confirmando', false)
            ->assertHasErrors(['proveedor_nombre' => 'required', 'lineas.0.producto_id' => 'required']);
    }

    public function test_el_producto_con_vencimiento_lo_exige_antes_de_revisar(): void
    {
        $this->compra($this->producto(['maneja_vencimiento' => true]))
            ->call('revisar')
            ->assertSet('confirmando', false)
            ->assertHasErrors(['lineas.0.fecha_vencimiento' => 'required']);
    }

    public function test_volver_a_editar_conserva_lo_escrito(): void
    {
        $this->compra()
            ->call('revisar')
            ->call('volverAEditar')
            ->assertSet('confirmando', false)
            ->assertSet('proveedor_nombre', 'Agro Andina SAC')
            ->assertSet('lineas.0.cantidad', 4)
            ->assertSet('lineas.0.costo_unitario', '5.50')
            ->assertDontSee('Verifique la compra');

        $this->assertDatabaseCount('compras', 0);
    }

    public function test_agregar_compra_desde_la_revision_guarda_y_deja_el_formulario_limpio(): void
    {
        $this->compra()
            ->call('revisar')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('confirmando', false)
            ->assertSet('creado', true)
            ->assertSet('proveedor_nombre', '')
            ->assertSet('proveedor_correo', '')
            ->assertSet('proveedor_direccion', '');

        $compra = Compra::firstOrFail();
        $this->assertSame('22.00', $compra->total);
        $this->assertSame(1, $compra->detalles()->count());
    }

    public function test_editar_un_dato_estando_en_la_revision_obliga_a_revisar_de_nuevo(): void
    {
        $this->compra()
            ->call('revisar')
            ->set('lineas.0.cantidad', 10)
            ->assertSet('confirmando', false);
    }

    public function test_el_formulario_envia_a_revisar_y_no_guarda_directo(): void
    {
        $html = Livewire::actingAs($this->admin())->test(CompraForm::class)->html();

        $this->assertStringContainsString('wire:submit="revisar"', $html);
        $this->assertStringContainsString('Revisar compra', $html);
    }
}
