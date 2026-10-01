<?php

namespace Database\Factories;

use App\Models\Almacen;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venta>
 */
class VentaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cliente_nombre' => $this->faker->name(),
            'cliente_documento' => $this->faker->numerify('########'),
            'almacen_id' => Almacen::factory(),
            'responsable_id' => User::factory(),
            'fecha' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'numero_comprobante' => strtoupper($this->faker->bothify('B001-####')),
            'total' => 0,
        ];
    }
}
