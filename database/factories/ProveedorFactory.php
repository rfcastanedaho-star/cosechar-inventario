<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->company(),
            'ruc' => $this->faker->unique()->numerify('20##########'),
            'telefono' => $this->faker->phoneNumber(),
            'direccion' => $this->faker->streetAddress(),
        ];
    }
}
