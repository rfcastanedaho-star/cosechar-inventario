<?php

use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\ProductoImagenController;
use App\Http\Controllers\ReporteController;
use App\Livewire\AlmacenForm;
use App\Livewire\AlmacenLista;
use App\Livewire\AuditoriaLista;
use App\Livewire\Auth\Login;
use App\Livewire\CambiarPassword;
use App\Livewire\CategoriaForm;
use App\Livewire\CategoriaLista;
use App\Livewire\ClienteForm;
use App\Livewire\ClienteLista;
use App\Livewire\CompraForm;
use App\Livewire\CompraLista;
use App\Livewire\DashboardGraficos;
use App\Livewire\MovimientoForm;
use App\Livewire\MovimientoLista;
use App\Livewire\ProductoForm;
use App\Livewire\ProductoLista;
use App\Livewire\ProveedorForm;
use App\Livewire\ProveedorLista;
use App\Livewire\ReporteFiltro;
use App\Livewire\UsuarioForm;
use App\Livewire\UsuarioLista;
use App\Livewire\VentaForm;
use App\Livewire\VentaLista;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::post('/logout', function () {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'activo'])->group(function () {
    Route::get('/dashboard', DashboardGraficos::class)->name('dashboard');
    Route::get('/productos', ProductoForm::class)->name('productos.index');
    Route::get('/productos/historial', ProductoLista::class)->name('productos.historial');
    Route::redirect('/productos/nuevo', '/productos');
    Route::get('/productos/{producto}/editar', ProductoForm::class)->name('productos.editar');
    Route::get('/productos/{producto}/imagen', [ProductoImagenController::class, 'mostrar'])->name('productos.imagen');
    Route::get('/movimientos/nuevo', MovimientoForm::class)->name('movimientos.nuevo');
    Route::get('/movimientos/historial', MovimientoLista::class)->name('movimientos.historial');
    Route::get('/almacenes', AlmacenForm::class)->name('almacenes.index');
    Route::get('/almacenes/historial', AlmacenLista::class)->name('almacenes.historial');
    Route::get('/almacenes/{almacen}/editar', AlmacenForm::class)->name('almacenes.editar');
    Route::get('/proveedores', ProveedorLista::class)->name('proveedores.index');
    Route::redirect('/proveedores/historial', '/proveedores');
    Route::get('/proveedores/{proveedor}/editar', ProveedorForm::class)->name('proveedores.editar');
    Route::get('/clientes', ClienteLista::class)->name('clientes.index');
    Route::get('/clientes/{cliente}/editar', ClienteForm::class)->name('clientes.editar');
    Route::get('/compras', CompraForm::class)->name('compras.index');
    Route::get('/compras/historial', CompraLista::class)->name('compras.historial');
    Route::get('/compras/{compra}/comprobante', [ComprobanteController::class, 'compra'])->name('compras.comprobante');
    Route::get('/ventas', VentaForm::class)->name('ventas.index');
    Route::get('/ventas/historial', VentaLista::class)->name('ventas.historial');
    Route::get('/ventas/{venta}/comprobante', [ComprobanteController::class, 'venta'])->name('ventas.comprobante');
    Route::get('/reportes', ReporteFiltro::class)->name('reportes');
    Route::get('/cuenta/contrasena', CambiarPassword::class)->name('password.cambiar');

    Route::middleware('can:ver-valorizacion')->group(function () {
        Route::get('/reportes/valorizacion/pdf', [ReporteController::class, 'valorizacionPdf'])->name('reportes.valorizacion.pdf');
        Route::get('/reportes/valorizacion/excel', [ReporteController::class, 'valorizacionExcel'])->name('reportes.valorizacion.excel');
    });

    Route::get('/auditoria', AuditoriaLista::class)->middleware('can:ver-auditoria')->name('auditoria.index');

    Route::middleware('can:gestionar-categorias')->group(function () {
        Route::get('/categorias', CategoriaLista::class)->name('categorias.index');
        Route::get('/categorias/nueva', CategoriaForm::class)->name('categorias.nueva');
        Route::get('/categorias/{categoria}/editar', CategoriaForm::class)->name('categorias.editar');
    });

    Route::middleware('can:gestionar-usuarios')->group(function () {
        Route::get('/usuarios', UsuarioLista::class)->name('usuarios.index');
        Route::get('/usuarios/nuevo', UsuarioForm::class)->name('usuarios.nuevo');
        Route::get('/usuarios/{usuario}/editar', UsuarioForm::class)->name('usuarios.editar');
    });

    Route::middleware('can:exportar-reportes')->group(function () {
        Route::get('/reportes/stock/pdf', [ReporteController::class, 'stockPdf'])->name('reportes.stock.pdf');
        Route::get('/reportes/stock/excel', [ReporteController::class, 'stockExcel'])->name('reportes.stock.excel');
        Route::get('/reportes/movimientos/pdf', [ReporteController::class, 'movimientosPdf'])->name('reportes.movimientos.pdf');
        Route::get('/reportes/movimientos/excel', [ReporteController::class, 'movimientosExcel'])->name('reportes.movimientos.excel');
        Route::get('/reportes/compras/pdf', [ReporteController::class, 'comprasPdf'])->name('reportes.compras.pdf');
        Route::get('/reportes/compras/excel', [ReporteController::class, 'comprasExcel'])->name('reportes.compras.excel');
        Route::get('/reportes/ventas/pdf', [ReporteController::class, 'ventasPdf'])->name('reportes.ventas.pdf');
        Route::get('/reportes/ventas/excel', [ReporteController::class, 'ventasExcel'])->name('reportes.ventas.excel');
    });
});
