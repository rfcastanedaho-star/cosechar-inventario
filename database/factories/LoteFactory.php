<?php

namespace Database\Factories;

use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lote>
 */
class LoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'almacen_id' => Almacen::factory(),
            'numero_lote' => strtoupper($this->faker->unique()->bothify('LOTE-####')),
            'fecha_ingreso' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'fecha_vencimiento' => null,
            'cantidad' => $this->faker->numberBetween(10, 100),
            'codigo_qr' => null,
        ];
    }

    public function ingresadoEl(string $fecha): static
    {
        return $this->state(fn () => ['fecha_ingreso' => $fecha]);
    }

    public function vence(string $fecha): static
    {
        return $this->state(fn () => ['fecha_vencimiento' => $fecha]);
    }

    public function conCantidad(int $cantidad): static
    {
        return $this->state(fn () => ['cantidad' => $cantidad]);
    }
}
