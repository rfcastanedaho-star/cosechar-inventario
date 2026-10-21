<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginasDeErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_ruta_inexistente_muestra_la_pagina_404_de_cosechar(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/esta-ruta-no-existe')
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertSee('COSECHAR')
            ->assertSee('Volver al inicio')
            ->assertSee(url('/'), false);
    }

    public function test_un_visitante_tambien_ve_la_pagina_404_y_se_le_ofrece_ingresar(): void
    {
        $this->get('/esta-ruta-no-existe')
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertSee('Volver al inicio');
    }

    public function test_sin_permiso_se_muestra_la_pagina_403_explicativa(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'operador_almacen']))
            ->get(route('usuarios.index'))
            ->assertForbidden()
            ->assertSee('Acceso denegado')
            ->assertSee('No tienes permiso');
    }

    public function test_la_pagina_de_error_no_filtra_detalles_tecnicos(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'operador_almacen']))
            ->get(route('usuarios.index'))
            ->assertDontSee('Illuminate')
            ->assertDontSee('vendor')
            ->assertDontSee('Stack trace');
    }

    /** @return array<string, array{int, string}> */
    public static function erroresConVista(): array
    {
        return [
            '419 sesión expirada' => [419, 'sesión'],
            '429 demasiadas peticiones' => [429, 'Demasiadas solicitudes'],
            '500 error del servidor' => [500, 'Algo salió mal'],
            '503 en mantenimiento' => [503, 'mantenimiento'],
        ];
    }

    #[DataProvider('erroresConVista')]
    public function test_existen_paginas_para_los_demas_errores_comunes(int $codigo, string $texto): void
    {
        $html = view('errors.'.$codigo)->render();

        $this->assertStringContainsString((string) $codigo, $html);
        $this->assertStringContainsString($texto, $html);
        $this->assertStringContainsString('COSECHAR', $html);
    }
}
