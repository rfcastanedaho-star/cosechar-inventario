<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Administrador Cosechar',
            'email' => 'admin@cosechar.test',
            'password' => Hash::make('password123'),
            'rol' => 'administrador',
        ]);

        User::create([
            'name' => 'Operador Almacén',
            'email' => 'operador@cosechar.test',
            'password' => Hash::make('password123'),
            'rol' => 'operador_almacen',
        ]);
    }
}