<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un usuario desactivado pierde el acceso al instante, aunque tenga la
 * sesión abierta o un token de la API: se le cierra la sesión (web) o se
 * rechaza la petición (API).
 */
class AsegurarUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->activo) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Usuario desactivado.'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
