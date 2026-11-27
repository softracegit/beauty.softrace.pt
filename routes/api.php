<?php

use App\Http\Controllers\Api\V1\BookingCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — app móvel de marcações (só leitura, pública)
|--------------------------------------------------------------------------
|
| Prefixo /api (bootstrap/app.php). Não usa sessão nem CSRF.
| Limite: 60 pedidos por minuto por IP.
|
*/

Route::prefix('v1')
    ->middleware(['throttle:60,1'])
    ->name('api.v1.')
    ->group(function (): void {
        Route::prefix('stores/{store:slug}')
            ->middleware('api.booking.store')
            ->name('stores.')
            ->group(function (): void {
                Route::get('/', [BookingCatalogController::class, 'store'])->name('show');
                Route::get('/catalog', [BookingCatalogController::class, 'catalog'])->name('catalog');
                Route::get('/technicians', [BookingCatalogController::class, 'technicians'])->name('technicians');
                Route::get('/availability', [BookingCatalogController::class, 'availability'])->name('availability');
            });
    });
