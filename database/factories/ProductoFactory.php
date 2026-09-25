<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper($this->faker->unique()->bothify('PROD-####')),
            'nombre' => ucfirst($this->faker->words(2, true)),
            'categoria_id' => Categoria::factory(),
            'unidad_medida' => $this->faker->randomElement(['saco', 'kg', 'litro', 'galon', 'unidad']),
            'stock_minimo' => $this->faker->numberBetween(0, 20),
            'precio' => $this->faker->randomFloat(2, 1, 500),
            'maneja_vencimiento' => false,
        ];
    }

    public function conVencimiento(): static
    {
        return $this->state(fn () => ['maneja_vencimiento' => true]);
    }
}
