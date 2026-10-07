<?php

use App\Http\Controllers\LoteController;
use App\Http\Controllers\ReporteController;
use App\Livewire\AlmacenForm;
use App\Livewire\AlmacenLista;
use App\Livewire\Auth\Login;
use App\Livewire\CompraForm;
use App\Livewire\CompraLista;
use App\Livewire\DashboardGraficos;
use App\Livewire\MovimientoForm;
use App\Livewire\ProductoForm;
use App\Livewire\ProductoLista;
use App\Livewire\ProveedorForm;
use App\Livewire\ProveedorLista;
use App\Livewire\ReporteFiltro;
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

Route::get('/lotes/{lote}/consulta', [LoteController::class, 'consulta'])->name('lotes.consulta');

Route::post('/logout', function () {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardGraficos::class)->name('dashboard');
    Route::get('/productos', ProductoLista::class)->name('productos.index');
    Route::get('/productos/nuevo', ProductoForm::class)->name('productos.nuevo');
    Route::get('/movimientos/nuevo', MovimientoForm::class)->name('movimientos.nuevo');
    Route::get('/lotes/{lote}/etiqueta', [LoteController::class, 'etiqueta'])->name('lotes.etiqueta');
    Route::get('/almacenes', AlmacenLista::class)->name('almacenes.index');
    Route::get('/almacenes/nuevo', AlmacenForm::class)->name('almacenes.nuevo');
    Route::get('/almacenes/{almacen}/editar', AlmacenForm::class)->name('almacenes.editar');
    Route::get('/proveedores', ProveedorLista::class)->name('proveedores.index');
    Route::get('/proveedores/nuevo', ProveedorForm::class)->name('proveedores.nuevo');
    Route::get('/proveedores/{proveedor}/editar', ProveedorForm::class)->name('proveedores.editar');
    Route::get('/compras', CompraLista::class)->name('compras.index');
    Route::get('/compras/nueva', CompraForm::class)->name('compras.nueva');
    Route::get('/ventas', VentaForm::class)->name('ventas.index');
    Route::get('/ventas/historial', VentaLista::class)->name('ventas.historial');
    Route::get('/reportes', ReporteFiltro::class)->name('reportes');

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
