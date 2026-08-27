<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WebhookController;

Route::post('/webhook/paystack', [WebhookController::class, 'handlePaystack']);
Route::post('/webhook/flutterwave', [WebhookController::class, 'handleFlutterwave']);

Route::middleware('jwt.verify')->group(function () {

    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'getBalance']);
        Route::post('/fund', [WalletController::class, 'fund']);
        Route::get('/history', [WalletController::class, 'history']);
    });

    Route::prefix('purchase')->group(function () {
        Route::post('/airtime', [PurchaseController::class, 'buyAirtime']);
        Route::post('/data', [PurchaseController::class, 'buyData']);
        Route::post('/electricity', [PurchaseController::class, 'buyElectricity']);
        Route::post('/cable', [PurchaseController::class, 'buyCable']);
    });

    Route::get('/services', [ServiceController::class, 'listServices']);
    Route::get('/services/{id}/products', [ServiceController::class, 'listProducts']);

    Route::get('/transactions', [TransactionController::class, 'history']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
});
