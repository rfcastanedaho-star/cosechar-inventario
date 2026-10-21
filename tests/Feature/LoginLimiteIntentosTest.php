<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class LoginLimiteIntentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login:ana@cosechar.test|127.0.0.1');
        RateLimiter::clear('login:luis@cosechar.test|127.0.0.1');
    }

    private function intentar(string $correo, string $clave): Testable
    {
        return Livewire::test(Login::class)
            ->set('email', $correo)
            ->set('password', $clave)
            ->call('autenticar');
    }

    public function test_cinco_intentos_fallidos_bloquean_el_sexto_aunque_la_clave_sea_correcta(): void
    {
        User::factory()->create(['email' => 'ana@cosechar.test', 'password' => 'clave-correcta-1']);

        foreach (range(1, 5) as $n) {
            $this->intentar('ana@cosechar.test', 'incorrecta-'.$n)->assertSee('Las credenciales no coinciden');
        }

        $this->intentar('ana@cosechar.test', 'clave-correcta-1')
            ->assertHasErrors(['email'])
            ->assertSee('Demasiados intentos');

        $this->assertGuest();
    }

    public function test_el_aviso_indica_cuanto_hay_que_esperar(): void
    {
        User::factory()->create(['email' => 'ana@cosechar.test']);

        foreach (range(1, 5) as $n) {
            $this->intentar('ana@cosechar.test', 'mal-'.$n);
        }

        $this->intentar('ana@cosechar.test', 'mal-6')->assertSeeHtml('segundos');
    }

    public function test_un_login_correcto_reinicia_el_contador(): void
    {
        User::factory()->create(['email' => 'ana@cosechar.test', 'password' => 'clave-correcta-1']);

        foreach (range(1, 4) as $n) {
            $this->intentar('ana@cosechar.test', 'mal-'.$n);
        }

        $this->intentar('ana@cosechar.test', 'clave-correcta-1')->assertHasNoErrors();
        $this->assertAuthenticated();

        auth()->logout();

        foreach (range(1, 4) as $n) {
            $this->intentar('ana@cosechar.test', 'otra-mala-'.$n)->assertDontSee('Demasiados intentos');
        }
    }

    public function test_el_limite_es_por_correo_y_no_afecta_a_otros_usuarios(): void
    {
        User::factory()->create(['email' => 'ana@cosechar.test']);
        User::factory()->create(['email' => 'luis@cosechar.test', 'password' => 'clave-de-luis-1']);

        foreach (range(1, 5) as $n) {
            $this->intentar('ana@cosechar.test', 'mal-'.$n);
        }

        $this->intentar('luis@cosechar.test', 'clave-de-luis-1')->assertHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_el_correo_se_normaliza_para_que_no_se_pueda_esquivar_el_limite(): void
    {
        User::factory()->create(['email' => 'ana@cosechar.test', 'password' => 'clave-correcta-1']);

        foreach (['ana@cosechar.test', 'ANA@cosechar.test', ' Ana@Cosechar.test', 'ana@COSECHAR.test', 'aNa@cosechar.test'] as $variante) {
            $this->intentar($variante, 'mal');
        }

        $this->intentar('ana@cosechar.test', 'clave-correcta-1')->assertSee('Demasiados intentos');
    }

    public function test_los_intentos_con_correos_inexistentes_tambien_cuentan(): void
    {
        foreach (range(1, 5) as $n) {
            $this->intentar('ana@cosechar.test', 'mal-'.$n);
        }

        $this->intentar('ana@cosechar.test', 'mal-6')->assertSee('Demasiados intentos');
    }

    public function test_un_usuario_inactivo_con_clave_correcta_no_gasta_intentos_de_mas(): void
    {
        User::factory()->create(['email' => 'ana@cosechar.test', 'password' => 'clave-correcta-1', 'activo' => false]);

        $this->intentar('ana@cosechar.test', 'clave-correcta-1')->assertSee('desactivado');
        $this->intentar('ana@cosechar.test', 'clave-correcta-1')->assertSee('desactivado');
    }
}
