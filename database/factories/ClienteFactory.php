<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->name(),
            'documento' => $this->faker->unique()->numerify('########'),
            'telefono' => $this->faker->numerify('9########'),
            'direccion' => $this->faker->streetAddress(),
            'activo' => true,
        ];
    }
}
