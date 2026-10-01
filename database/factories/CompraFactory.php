<?php

namespace Database\Factories;

use App\Models\Almacen;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compra>
 */
class CompraFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proveedor_id' => Proveedor::factory(),
            'almacen_id' => Almacen::factory(),
            'responsable_id' => User::factory(),
            'fecha' => $this->faker->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'numero_comprobante' => strtoupper($this->faker->bothify('F001-####')),
            'total' => 0,
        ];
    }
}
