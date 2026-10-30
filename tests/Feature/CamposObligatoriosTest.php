<?php

namespace Tests\Feature;

use App\Livewire\AlmacenForm;
use App\Livewire\CambiarPassword;
use App\Livewire\CategoriaForm;
use App\Livewire\ClienteForm;
use App\Livewire\CompraForm;
use App\Livewire\MovimientoForm;
use App\Livewire\ProductoForm;
use App\Livewire\ProveedorForm;
use App\Livewire\UsuarioForm;
use App\Livewire\VentaForm;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CamposObligatoriosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cada formulario: clase, estado previo opcional, etiquetas que deben llevar *
     * y etiquetas que son opcionales y no deben llevarlo.
     *
     * @return array<string, array{class-string, array<string, mixed>, array<int, string>, array<int, string>}>
     */
    public static function formularios(): array
    {
        return [
            'producto' => [ProductoForm::class, [], ['Código *', 'Nombre *', 'Categoría *', 'Unidad de medida *', 'Stock mínimo *', 'Precio (S/) *', 'Precio costo (S/) *'], ['Stock inicial *', 'Marca *', 'Presentación *', 'Foto del producto *']],
            'compra' => [CompraForm::class, [], ['Nombre del proveedor *', 'Almacén destino *', 'Fecha *', 'Producto *', 'N.º de lote *', 'Cantidad *', 'Costo unitario (S/) *'], ['RUC (opcional) *', 'Teléfono (opcional) *', 'comprobante (opcional) *']],
            'venta' => [VentaForm::class, [], ['Cliente *', 'Almacén de origen *', 'Fecha *', 'Producto *', 'Cantidad *', 'Precio unitario (S/) *'], ['Documento (opcional) *', 'comprobante (opcional) *']],
            'movimiento entrada' => [MovimientoForm::class, ['tipo' => 'entrada'], ['Producto *', 'Lote *', 'Cantidad *'], []],
            'movimiento salida' => [MovimientoForm::class, ['tipo' => 'salida'], ['Producto *', 'Motivo de la salida *', 'Cantidad *'], []],
            'usuario' => [UsuarioForm::class, [], ['Nombre *', 'Correo *', 'Rol *', 'Contraseña *', 'Repetir contraseña *'], []],
            'categoria' => [CategoriaForm::class, [], ['Nombre *'], ['Grupo *']],
            'almacen' => [AlmacenForm::class, [], ['Nombre *'], ['Dirección *', 'Encargado *']],
            'cambiar contraseña' => [CambiarPassword::class, [], ['Contraseña actual *', 'Nueva contraseña *', 'Repetir la nueva contraseña *'], []],
        ];
    }

    private function texto(string $clase, array $estado, array $parametros = []): string
    {
        $componente = Livewire::actingAs(User::factory()->create(['rol' => 'administrador']))->test($clase, $parametros);

        foreach ($estado as $propiedad => $valor) {
            $componente->set($propiedad, $valor);
        }

        // Se compara el texto visible, sin etiquetas, con los espacios unificados.
        return preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($componente->html()), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * @param  array<string, mixed>  $estado
     * @param  array<int, string>  $obligatorias
     * @param  array<int, string>  $opcionales
     */
    #[DataProvider('formularios')]
    public function test_cada_formulario_marca_con_asterisco_lo_obligatorio_y_explica_el_simbolo(string $clase, array $estado, array $obligatorias, array $opcionales): void
    {
        $texto = $this->texto($clase, $estado);

        $this->assertStringContainsString('Los campos con * son obligatorios', $texto);

        foreach ($obligatorias as $etiqueta) {
            $this->assertStringContainsString($etiqueta, $texto, "Falta el asterisco en: {$etiqueta}");
        }

        foreach ($opcionales as $etiqueta) {
            $this->assertStringNotContainsString($etiqueta, $texto, "No debería llevar asterisco: {$etiqueta}");
        }
    }

    public function test_el_formulario_de_cliente_marca_el_nombre_y_deja_el_resto_como_opcional(): void
    {
        $texto = $this->texto(ClienteForm::class, [], ['cliente' => Cliente::factory()->create()]);

        $this->assertStringContainsString('Los campos con * son obligatorios', $texto);
        $this->assertStringContainsString('Nombre o razón social *', $texto);
        $this->assertStringContainsString('DNI o RUC (opcional)', $texto);
        $this->assertStringNotContainsString('(opcional) *', $texto);
    }

    public function test_el_formulario_de_proveedor_marca_el_nombre_y_deja_el_resto_como_opcional(): void
    {
        $texto = $this->texto(ProveedorForm::class, [], ['proveedor' => Proveedor::factory()->create()]);

        $this->assertStringContainsString('Los campos con * son obligatorios', $texto);
        $this->assertStringContainsString('Nombre / Razón social *', $texto);
        $this->assertStringContainsString('RUC (opcional)', $texto);
        $this->assertStringNotContainsString('(opcional) *', $texto);
    }

    public function test_en_compras_el_vencimiento_solo_es_obligatorio_para_productos_que_lo_manejan(): void
    {
        $vence = Producto::factory()->create(['maneja_vencimiento' => true]);
        $noVence = Producto::factory()->create(['maneja_vencimiento' => false]);

        $componente = Livewire::actingAs(User::factory()->create())->test(CompraForm::class);

        $normalizar = fn (string $html) => preg_replace('/\s+/u', ' ', strip_tags($html));

        $this->assertStringContainsString('Vencimiento (si aplica)', $normalizar($componente->html()));

        $componente->call('elegirProductoLinea', 0, $vence->id);
        $this->assertStringContainsString('Vencimiento *', $normalizar($componente->html()));

        $componente->call('limpiarProductoLinea', 0)->call('elegirProductoLinea', 0, $noVence->id);
        $this->assertStringContainsString('Vencimiento (si aplica)', $normalizar($componente->html()));
    }

    public function test_la_pantalla_de_edicion_de_usuario_marca_tambien_la_nueva_contrasena(): void
    {
        $texto = $this->texto(UsuarioForm::class, [], ['usuario' => User::factory()->create(['rol' => 'operador_almacen'])]);

        $this->assertStringContainsString('Nueva contraseña *', $texto);
        $this->assertStringContainsString('Repetir contraseña *', $texto);
    }
}
