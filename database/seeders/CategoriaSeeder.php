<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $fertilizantes = Categoria::create(['nombre' => 'Fertilizantes']);
        $semillas = Categoria::create(['nombre' => 'Semillas']);
        $herramientas = Categoria::create(['nombre' => 'Herramientas']);
        $agroquimicos = Categoria::create(['nombre' => 'Agroquímicos']);

        Categoria::create([
            'nombre' => 'Fungicida',
            'categoria_padre_id' => $agroquimicos->id,
        ]);

        Categoria::create([
            'nombre' => 'Insecticida',
            'categoria_padre_id' => $agroquimicos->id,
        ]);

        Categoria::create([
            'nombre' => 'Herbicida',
            'categoria_padre_id' => $agroquimicos->id,
        ]);

        Categoria::create([
            'nombre' => 'Abono foliar',
            'categoria_padre_id' => $agroquimicos->id,
        ]);
    }
}