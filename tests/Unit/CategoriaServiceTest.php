<?php

namespace Tests\Unit;

use App\Exceptions\CategoriaNoPermitidaException;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Services\CategoriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriaServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoriaService $servicio;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(CategoriaService::class);
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_crea_un_grupo_principal_y_una_subcategoria(): void
    {
        $grupo = $this->servicio->crear('Equipos de campo', null, $this->admin);
        $sub = $this->servicio->crear('Palas', $grupo->id, $this->admin);

        $this->assertNull($grupo->categoria_padre_id);
        $this->assertSame($grupo->id, $sub->categoria_padre_id);
    }

    public function test_el_nombre_se_limpia_de_espacios(): void
    {
        $this->assertSame('Equipos de campo', $this->servicio->crear('   Equipos de campo  ', null, $this->admin)->nombre);
    }

    public function test_no_acepta_un_nombre_repetido_entre_hermanas_sin_importar_mayusculas(): void
    {
        $grupo = Categoria::factory()->create(['nombre' => 'Semillas']);
        Categoria::factory()->create(['nombre' => 'Hortalizas', 'categoria_padre_id' => $grupo->id]);

        $this->expectException(CategoriaNoPermitidaException::class);

        $this->servicio->crear('hortalizas', $grupo->id, $this->admin);
    }

    public function test_el_mismo_nombre_puede_existir_bajo_grupos_distintos(): void
    {
        $a = Categoria::factory()->create(['nombre' => 'Grupo A']);
        $b = Categoria::factory()->create(['nombre' => 'Grupo B']);
        Categoria::factory()->create(['nombre' => 'Otros', 'categoria_padre_id' => $a->id]);

        $this->assertSame('Otros', $this->servicio->crear('Otros', $b->id, $this->admin)->nombre);
    }

    public function test_un_grupo_principal_no_puede_repetir_el_nombre_de_otro_grupo(): void
    {
        Categoria::factory()->create(['nombre' => 'Semillas']);

        $this->expectException(CategoriaNoPermitidaException::class);

        $this->servicio->crear('SEMILLAS', null, $this->admin);
    }

    public function test_solo_hay_dos_niveles_la_subcategoria_no_puede_tener_hijas(): void
    {
        $grupo = Categoria::factory()->create();
        $sub = Categoria::factory()->create(['categoria_padre_id' => $grupo->id]);

        $this->expectException(CategoriaNoPermitidaException::class);

        $this->servicio->crear('Tercer nivel', $sub->id, $this->admin);
    }

    public function test_no_se_puede_crear_una_subcategoria_dentro_de_una_categoria_que_ya_tiene_productos(): void
    {
        $categoria = Categoria::factory()->create();
        Producto::factory()->create(['categoria_id' => $categoria->id]);

        $this->expectException(CategoriaNoPermitidaException::class);

        $this->servicio->crear('Subcategoría', $categoria->id, $this->admin);
    }

    public function test_un_padre_inexistente_se_rechaza(): void
    {
        $this->expectException(CategoriaNoPermitidaException::class);

        $this->servicio->crear('Huérfana', 99999, $this->admin);
    }

    public function test_renombrar_deja_historial(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Fungicidas']);

        $this->servicio->renombrar($categoria, 'Fungicidas agrícolas', $this->admin);

        $this->assertSame('Fungicidas agrícolas', $categoria->fresh()->nombre);
        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Categoria::class, 'auditable_id' => $categoria->id, 'user_id' => $this->admin->id,
            'campo' => 'nombre', 'valor_anterior' => 'Fungicidas', 'valor_nuevo' => 'Fungicidas agrícolas',
        ]);
    }

    public function test_renombrar_no_puede_chocar_con_una_hermana_pero_si_conservar_el_propio_nombre(): void
    {
        $grupo = Categoria::factory()->create();
        Categoria::factory()->create(['nombre' => 'Palas', 'categoria_padre_id' => $grupo->id]);
        $picos = Categoria::factory()->create(['nombre' => 'Picos', 'categoria_padre_id' => $grupo->id]);

        $this->servicio->renombrar($picos, 'picos', $this->admin);
        $this->assertSame('picos', $picos->fresh()->nombre);

        $this->expectException(CategoriaNoPermitidaException::class);
        $this->servicio->renombrar($picos, 'PALAS', $this->admin);
    }

    public function test_eliminar_una_categoria_vacia_la_borra_y_lo_registra(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Obsoleta']);

        $this->servicio->eliminar($categoria, $this->admin);

        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
        $this->assertDatabaseHas('auditoria_cambios', [
            'auditable_type' => Categoria::class, 'auditable_id' => $categoria->id, 'campo' => 'nombre',
            'valor_anterior' => 'Obsoleta', 'valor_nuevo' => null,
        ]);
    }

    public function test_no_se_elimina_una_categoria_con_productos(): void
    {
        $categoria = Categoria::factory()->create();
        Producto::factory()->create(['categoria_id' => $categoria->id]);

        try {
            $this->servicio->eliminar($categoria, $this->admin);
            $this->fail('Debió lanzar CategoriaNoPermitidaException');
        } catch (CategoriaNoPermitidaException) {
            $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
        }
    }

    public function test_no_se_elimina_un_grupo_con_subcategorias(): void
    {
        $grupo = Categoria::factory()->create();
        Categoria::factory()->create(['categoria_padre_id' => $grupo->id]);

        $this->expectException(CategoriaNoPermitidaException::class);

        $this->servicio->eliminar($grupo, $this->admin);
    }
}
