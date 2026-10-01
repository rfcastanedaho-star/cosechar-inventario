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
    Route::get('/proveedores', ProveedorLista::class)->name('proveedores.index');
    Route::get('/proveedores/nuevo', ProveedorForm::class)->name('proveedores.nuevo');
    Route::get('/compras', CompraLista::class)->name('compras.index');
    Route::get('/compras/nueva', CompraForm::class)->name('compras.nueva');
    Route::get('/ventas', VentaLista::class)->name('ventas.index');
    Route::get('/ventas/nueva', VentaForm::class)->name('ventas.nueva');
    Route::get('/reportes', ReporteFiltro::class)->name('reportes');

    Route::middleware('can:exportar-reportes')->group(function () {
        Route::get('/reportes/stock/pdf', [ReporteController::class, 'stockPdf'])->name('reportes.stock.pdf');
        Route::get('/reportes/stock/csv', [ReporteController::class, 'stockCsv'])->name('reportes.stock.csv');
        Route::get('/reportes/movimientos/pdf', [ReporteController::class, 'movimientosPdf'])->name('reportes.movimientos.pdf');
        Route::get('/reportes/movimientos/csv', [ReporteController::class, 'movimientosCsv'])->name('reportes.movimientos.csv');
    });
});
