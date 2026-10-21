<?php

namespace Tests\Unit;

use App\Models\Almacen;
use App\Models\AuditoriaCambio;
use App\Models\Producto;
use App\Models\User;
use App\Repositories\AuditoriaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private AuditoriaRepository $repositorio;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = new AuditoriaRepository;
        $this->usuario = User::factory()->create();
    }

    private function cambio(string $tipo = Producto::class, int $id = 1, string $fecha = '2026-10-10 09:00:00', ?User $usuario = null): AuditoriaCambio
    {
        $cambio = AuditoriaCambio::create([
            'auditable_type' => $tipo, 'auditable_id' => $id, 'user_id' => ($usuario ?? $this->usuario)->id,
            'campo' => 'nombre', 'valor_anterior' => 'A', 'valor_nuevo' => 'B',
        ]);
        $cambio->forceFill(['created_at' => $fecha])->save();

        return $cambio;
    }

    public function test_ordena_del_mas_reciente_al_mas_antiguo(): void
    {
        $this->cambio(fecha: '2026-10-01 08:00:00');
        $this->cambio(fecha: '2026-10-09 08:00:00');
        $this->cambio(fecha: '2026-10-05 08:00:00');

        $fechas = $this->repositorio->historial()->pluck('created_at')->map->toDateString()->all();

        $this->assertSame(['2026-10-09', '2026-10-05', '2026-10-01'], $fechas);
    }

    public function test_pagina_los_resultados(): void
    {
        foreach (range(1, 12) as $n) {
            $this->cambio();
        }

        $pagina = $this->repositorio->historial([], 5);

        $this->assertSame(12, $pagina->total());
        $this->assertCount(5, $pagina->items());
    }

    public function test_filtra_por_entidad(): void
    {
        $this->cambio(Producto::class);
        $this->cambio(Almacen::class);

        $this->assertSame(1, $this->repositorio->historial(['entidad' => Almacen::class])->total());
        $this->assertSame(2, $this->repositorio->historial(['entidad' => ''])->total());
        $this->assertSame(2, $this->repositorio->historial([])->total());
    }

    public function test_filtra_por_usuario(): void
    {
        $otro = User::factory()->create();
        $this->cambio();
        $this->cambio(usuario: $otro);

        $this->assertSame(1, $this->repositorio->historial(['user_id' => $otro->id])->total());
    }

    public function test_filtra_por_rango_de_fechas_incluyendo_los_extremos(): void
    {
        $this->cambio(fecha: '2026-10-01 23:30:00');
        $this->cambio(fecha: '2026-10-05 10:00:00');
        $this->cambio(fecha: '2026-10-10 00:10:00');
        $this->cambio(fecha: '2026-10-11 09:00:00');

        $this->assertSame(3, $this->repositorio->historial(['desde' => '2026-10-01', 'hasta' => '2026-10-10'])->total());
        $this->assertSame(3, $this->repositorio->historial(['desde' => '2026-10-05'])->total());
        $this->assertSame(1, $this->repositorio->historial(['hasta' => '2026-10-01'])->total());
    }

    public function test_ignora_fechas_con_formato_invalido(): void
    {
        $this->cambio();

        $this->assertSame(1, $this->repositorio->historial(['desde' => 'no-es-fecha', 'hasta' => '99/99/99'])->total());
    }

    public function test_trae_usuario_y_registro_afectado_sin_consultas_extra(): void
    {
        $producto = Producto::factory()->create();
        $this->cambio(Producto::class, $producto->id);

        $primero = $this->repositorio->historial()->items()[0];

        $this->assertTrue($primero->relationLoaded('usuario'));
        $this->assertTrue($primero->relationLoaded('auditable'));
        $this->assertSame($producto->id, $primero->auditable->id);
    }

    public function test_un_registro_eliminado_no_rompe_el_historial(): void
    {
        $this->cambio(Producto::class, 99999);

        $primero = $this->repositorio->historial()->items()[0];

        $this->assertNull($primero->auditable);
    }

    public function test_lista_las_entidades_que_tienen_cambios_y_los_usuarios_que_los_hicieron(): void
    {
        $otro = User::factory()->create();
        $this->cambio(Producto::class);
        $this->cambio(Almacen::class, usuario: $otro);

        $this->assertEqualsCanonicalizing([Producto::class, Almacen::class], $this->repositorio->entidadesRegistradas());
        $this->assertEqualsCanonicalizing([$this->usuario->id, $otro->id], $this->repositorio->usuariosQueCambiaron()->pluck('id')->all());
    }
}
