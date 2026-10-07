<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Response;

class ProductoImagenController extends Controller
{
    public function mostrar(Producto $producto): Response
    {
        $imagen = $producto->imagen;

        abort_if($imagen === null, 404);

        // La URL lleva ?v=<fecha> en las vistas, así que el navegador puede guardarla un día.
        return response(base64_decode($imagen->contenido), 200, [
            'Content-Type' => $imagen->mime,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
