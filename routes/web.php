<?php

use App\Http\Controllers\ExitApplicationController;
use App\Http\Controllers\Farma\ProductPreviewController;
use App\Http\Controllers\SelectorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('ui/selectors/{source}', SelectorController::class)
        ->where('source', '[a-z0-9-]+')->name('ui.selectors');
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('pos/descuentos', 'pos.descuentos')->middleware('api.permission:discountspos.list')->name('pos.descuentos');
    Route::view('pos/compras', 'pos.compras')->middleware('api.permission:purchasespos.list')->name('pos.compras');
    Route::view('pos/usuarios', 'pos.usuarios')
        ->middleware('api.permission:usuariospos.list')
        ->name('pos.usuarios');
    Route::view('pos/roles', 'pos.roles')
        ->middleware('api.permission:rolespos.list')
        ->name('pos.roles');
    Route::view('pos/catalogos', 'pos.catalogos')
        ->middleware('api.permission:catalogospos.list')
        ->name('pos.catalogos');
    Route::view('pos/clientepos', 'pos.clientepos')
        ->middleware('api.permission:clientepos.list')
        ->name('pos.clientepos');
    Route::view('config/users', 'config.users')
        ->middleware('api.permission:users.list')
        ->name('config.users');
    Route::view('config/modules', 'config.modules')
        ->middleware('api.permission:modules.list')
        ->name('config.modules');
    Route::view('config/views', 'config.views')
        ->middleware('api.permission:views.list')
        ->name('config.views');
    Route::view('config/stocks', 'config.stocks')
        ->middleware('api.permission:stocks.list')
        ->name('config.stocks');
    Route::view('config/licenses', 'config.licenses')->middleware('api.permission:licenses.list')->name('config.licenses');
    Route::view('config/appconfig', 'config.app-config')
        ->middleware('api.permission:appconfig.list')->name('config.appconfig');
    Route::view('config/desktop-releases', 'config.desktop-releases')->middleware('api.permission:desktop-releases.list')->name('config.desktop-releases');
    Route::view('config/devices', 'config.devices')
        ->middleware('api.permission:devices.list')
        ->name('config.devices');
    Route::view('sorteos/lista-sorteos', 'sorteos.draws')
        ->middleware('api.permission:draws.list')
        ->name('sorteos.draws');
    Route::view('sorteos/generacion-tickets', 'sorteos.tickets')
        ->middleware('api.permission:tickets.list')
        ->name('sorteos.tickets');
    Route::view('reportes/sorteos', 'reportes.sorteos')
        ->middleware('api.permission:reports.draws.filter')
        ->name('reportes.sorteos');
    Route::view('reportes/jornada-laboral', 'reportes.jornada-laboral')
        ->middleware('api.permission:reports.worklunch.filter')
        ->name('reportes.jornada-laboral');
    Route::view('farma/stocks/matriz-inventario', 'farma.matriz-inventario')->middleware('api.permission:inventory-matrix.list')->name('farma.stocks.matriz-inventario');
    Route::view('farma/tareas', 'farma.tareas')->middleware('api.permission:tasks.list')->name('farma.tareas');
    Route::view('farma/proveedores', 'farma.proveedores')
        ->middleware('api.permission:providers.list')
        ->name('farma.proveedores');
    Route::view('farma/laboratorios', 'farma.laboratorios')
        ->middleware('api.permission:laboratories.list')
        ->name('farma.laboratorios');
    Route::view('farma/alertas-venta', 'farma.alertas-venta')
        ->middleware('api.permission:sale-alerts.list')->name('farma.alertas-venta');
    Route::view('farma/productos', 'farma.productos')
        ->middleware('api.permission:products.list')
        ->name('farma.productos');
    Route::view('farma/lotes', 'farma.lotes')
        ->middleware('api.permission:lots.list')
        ->name('farma.lotes');
    Route::view('farma/vencimientos', 'farma.vencimientos')
        ->middleware('api.permission:expiry.list')
        ->name('farma.vencimientos');
    Route::view('farma/reglas-proveedores', 'farma.reglas-proveedores')
        ->middleware('api.permission:supplier-rules.list')
        ->name('farma.reglas-proveedores');
    Route::view('farma/distribucion', 'farma.distribucion')
        ->middleware('api.permission:distribution.list')
        ->name('farma.distribucion');
    Route::view('farma/transferencias', 'farma.transferencias')
        ->middleware('api.permission:transfers.list')
        ->name('farma.transferencias');
    Route::view('farma/transferencias/tipos', 'farma.tipos-transferencia')
        ->middleware('api.permission:transfer-types.list')
        ->name('farma.transferencias.tipos');
    Route::get('farma/productos/{product}/preview', ProductPreviewController::class)
        ->whereNumber('product')
        ->middleware('api.permission:products.image')
        ->name('farma.products.preview');
    Route::view('farma/compras', 'farma.compras')
        ->middleware('api.permission:purchases.list')
        ->name('farma.compras');
    Route::view('farma/reportes/compras', 'farma.reporte-compras')
        ->middleware('api.permission:purchase-reports.list')
        ->name('farma.reportes.compras');
    Route::view('farma/reportes/costeo', 'farma.reporte-costeo')
        ->middleware('api.permission:cost-reports.list')->name('farma.reportes.costeo');
    Route::view('farma/reportes/ventas-perdidas', 'farma.ventas-perdidas')
        ->middleware('api.permission:lost-sales.list')->name('farma.reportes.ventas-perdidas');
    Route::view('farma/compras/transacciones', 'farma.compra-transacciones')
        ->middleware('api.permission:purchase-transactions.list')
        ->name('farma.compras.transacciones');

    foreach (config('farma_purchase_catalogs', []) as $catalog => $definition) {
        Route::view("farma/compras/{$catalog}", 'farma.purchase-catalog', [
            'catalog' => $catalog,
            'title' => $definition['label'],
        ])
            ->middleware("api.permission:purchase-catalogs.{$catalog}.list")
            ->name($definition['route']);
    }

    foreach (config('farma_product_catalogs', []) as $catalog => $definition) {
        if (! is_string($catalog) || ! is_array($definition)) {
            continue;
        }

        $routeName = $definition['route'] ?? null;
        $title = $definition['label'] ?? null;

        if (! is_string($routeName) || ! is_string($title)) {
            continue;
        }

        Route::view("farma/productos/{$catalog}", 'farma.product-catalog', [
            'catalog' => $catalog,
            'title' => $title,
        ])
            ->middleware("api.permission:product-catalogs.{$catalog}.list")
            ->name($routeName);
    }

    Route::post('app/exit', ExitApplicationController::class)->name('app.exit');
});

require __DIR__.'/settings.php';
