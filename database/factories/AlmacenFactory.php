<?php

namespace Database\Factories;

use App\Models\Almacen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Almacen>
 */
class AlmacenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Almacén '.ucfirst($this->faker->unique()->city()),
            'direccion' => $this->faker->streetAddress(),
            'encargado' => $this->faker->name(),
            'activo' => true,
        ];
    }
}
