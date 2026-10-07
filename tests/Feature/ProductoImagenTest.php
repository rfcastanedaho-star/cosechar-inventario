<?php

namespace Tests\Feature;

use App\Livewire\ProductoForm;
use App\Livewire\ProductoLista;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\User;
use App\Repositories\ProductoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoImagenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function registrarProducto(User $usuario, ?UploadedFile $imagen = null)
    {
        $categoria = Categoria::factory()->create();

        $componente = Livewire::actingAs($usuario)
            ->test(ProductoForm::class)
            ->set('codigo', 'IMG-1')
            ->set('nombre', 'Producto con foto')
            ->set('categoria_id', $categoria->id)
            ->set('unidad_medida', 'kg')
            ->set('stock_minimo', 1)
            ->set('precio', '5');

        if ($imagen) {
            $componente->set('imagen', $imagen);
        }

        return $componente->call('guardar');
    }

    public function test_el_formulario_guarda_la_foto_reducida_a_480px(): void
    {
        $usuario = User::factory()->create();

        $this->registrarProducto($usuario, UploadedFile::fake()->image('foto.jpg', 1200, 800))->assertHasNoErrors();

        $imagen = Producto::where('codigo', 'IMG-1')->firstOrFail()->imagen;

        $this->assertNotNull($imagen);
        $this->assertContains($imagen->mime, ['image/webp', 'image/png']);

        [$ancho, $alto] = getimagesizefromstring(base64_decode($imagen->contenido));
        $this->assertSame(480, max($ancho, $alto));
        $this->assertSame(320, min($ancho, $alto));
    }

    public function test_una_foto_pequena_no_se_agranda(): void
    {
        $usuario = User::factory()->create();

        $this->registrarProducto($usuario, UploadedFile::fake()->image('chica.png', 200, 100));

        [$ancho, $alto] = getimagesizefromstring(base64_decode(Producto::where('codigo', 'IMG-1')->firstOrFail()->imagen->contenido));
        $this->assertSame(200, $ancho);
        $this->assertSame(100, $alto);
    }

    public function test_la_foto_es_opcional(): void
    {
        $usuario = User::factory()->create();

        $this->registrarProducto($usuario)->assertHasNoErrors();

        $this->assertDatabaseHas('productos', ['codigo' => 'IMG-1']);
        $this->assertSame(0, ProductoImagen::count());
    }

    public function test_rechaza_archivos_que_no_son_imagen(): void
    {
        $usuario = User::factory()->create();

        $this->registrarProducto($usuario, UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'))
            ->assertHasErrors(['imagen']);

        $this->assertDatabaseMissing('productos', ['codigo' => 'IMG-1']);
    }

    public function test_rechaza_fotos_de_mas_de_4_mb(): void
    {
        $usuario = User::factory()->create();

        $this->registrarProducto($usuario, UploadedFile::fake()->image('enorme.jpg', 800, 800)->size(5000))
            ->assertHasErrors(['imagen']);
    }

    public function test_la_ruta_sirve_la_foto_con_su_tipo(): void
    {
        $usuario = User::factory()->create();
        $this->registrarProducto($usuario, UploadedFile::fake()->image('foto.jpg', 600, 600));
        $producto = Producto::where('codigo', 'IMG-1')->firstOrFail();

        $respuesta = $this->actingAs($usuario)->get(route('productos.imagen', $producto))->assertOk();

        $this->assertSame($producto->imagen->mime, $respuesta->headers->get('Content-Type'));
        $this->assertNotFalse(getimagesizefromstring($respuesta->getContent()));
    }

    public function test_la_ruta_responde_404_si_el_producto_no_tiene_foto(): void
    {
        $usuario = User::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs($usuario)->get(route('productos.imagen', $producto))->assertNotFound();
    }

    public function test_un_visitante_sin_sesion_no_ve_las_fotos(): void
    {
        $producto = Producto::factory()->create();

        $this->get(route('productos.imagen', $producto))->assertRedirect(route('login'));
    }

    public function test_se_puede_agregar_y_reemplazar_la_foto_desde_la_lista(): void
    {
        $usuario = User::factory()->create();
        $producto = Producto::factory()->create();

        $lista = Livewire::actingAs($usuario)->test(ProductoLista::class);

        $lista->set('productoFotoId', $producto->id)
            ->set('fotoNueva', UploadedFile::fake()->image('uno.jpg', 700, 500))
            ->assertHasNoErrors();

        $primera = $producto->fresh()->imagen->contenido;
        $this->assertSame(1, ProductoImagen::count());

        $lista->set('productoFotoId', $producto->id)
            ->set('fotoNueva', UploadedFile::fake()->image('dos.png', 300, 900))
            ->assertHasNoErrors();

        $this->assertSame(1, ProductoImagen::count());
        $this->assertNotSame($primera, $producto->fresh()->imagen->contenido);
    }

    public function test_la_lista_rechaza_una_foto_invalida(): void
    {
        $usuario = User::factory()->create();
        $producto = Producto::factory()->create();

        Livewire::actingAs($usuario)
            ->test(ProductoLista::class)
            ->set('productoFotoId', $producto->id)
            ->set('fotoNueva', UploadedFile::fake()->create('virus.exe', 50, 'application/octet-stream'))
            ->assertHasErrors(['fotoNueva']);

        $this->assertSame(0, ProductoImagen::count());
    }

    public function test_la_consulta_de_stock_indica_que_productos_tienen_foto(): void
    {
        $usuario = User::factory()->create();
        $conFoto = Producto::factory()->create();
        $sinFoto = Producto::factory()->create();

        Livewire::actingAs($usuario)
            ->test(ProductoLista::class)
            ->set('productoFotoId', $conFoto->id)
            ->set('fotoNueva', UploadedFile::fake()->image('a.jpg', 400, 400));

        $productos = (new ProductoRepository)->conStockActual()->keyBy('id');

        $this->assertNotNull($productos[$conFoto->id]->imagen_version);
        $this->assertNull($productos[$sinFoto->id]->imagen_version);
    }
}
