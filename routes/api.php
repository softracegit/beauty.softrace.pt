<?php

use App\Http\Controllers\Api\V1\BookingCatalogController;
use App\Http\Controllers\Api\V1\ClientAccountController;
use App\Http\Controllers\Api\V1\ClientAuthController;
use App\Http\Controllers\Api\V1\ClientBookingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — app móvel de marcações
|--------------------------------------------------------------------------
|
| Prefixo /api (bootstrap/app.php). Sem cookies nem CSRF.
| Cada limite tem um prefixo próprio (3.º parâmetro do throttle), senão
| todos partilhariam o mesmo contador por IP.
| Fase 1: catálogo e horários (públicos, só leitura).
| Fase 2: entrada com código, conta e marcações (token "Bearer" da app).
|
*/

Route::prefix('v1')
    ->middleware(['throttle:60,1,api_v1'])
    ->name('api.v1.')
    ->group(function (): void {
        Route::prefix('stores/{store:slug}')
            ->middleware('api.booking.store')
            ->name('stores.')
            ->group(function (): void {
                // Público
                Route::get('/', [BookingCatalogController::class, 'store'])->name('show');
                Route::get('/catalog', [BookingCatalogController::class, 'catalog'])->name('catalog');
                Route::get('/technicians', [BookingCatalogController::class, 'technicians'])->name('technicians');
                Route::get('/availability', [BookingCatalogController::class, 'availability'])->name('availability');

                // Entrada com código (mesmos limites do booking web)
                Route::middleware('api.array_session')->prefix('auth')->name('auth.')->group(function (): void {
                    Route::post('/request-code', [ClientAuthController::class, 'requestCode'])
                        ->middleware('throttle:20,1,api_auth_request')->name('request_code');
                    Route::post('/verify-code', [ClientAuthController::class, 'verifyCode'])
                        ->middleware('throttle:10,1,api_auth_verify')->name('verify_code');
                    Route::post('/complete-registration', [ClientAuthController::class, 'completeRegistration'])
                        ->middleware('throttle:10,1,api_auth_register')->name('complete_registration');
                });

                // Com sessão da app
                Route::middleware('api.client')->group(function (): void {
                    Route::post('/auth/logout', [ClientAuthController::class, 'logout'])->name('auth.logout');
                    Route::get('/me', [ClientAccountController::class, 'show'])->name('me');
                    Route::patch('/me', [ClientAccountController::class, 'update'])
                        ->middleware('throttle:30,1,api_me_update')->name('me.update');
                    Route::get('/me/bookings', [ClientAccountController::class, 'bookings'])->name('me.bookings');
                    Route::post('/bookings', [ClientBookingController::class, 'store'])
                        ->middleware('throttle:30,1,api_booking_store')->name('bookings.store');
                    Route::post('/bookings/{booking}/cancel', [ClientBookingController::class, 'cancel'])
                        ->whereNumber('booking')
                        ->middleware('throttle:30,1,api_booking_cancel')->name('bookings.cancel');
                });
            });
    });
