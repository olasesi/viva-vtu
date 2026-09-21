<?php

use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/paystack', [WebhookController::class, 'handlePaystack']);
Route::post('/webhook/flutterwave', [WebhookController::class, 'handleFlutterwave']);
Route::post('/webhook/aggregator/{slug}', [WebhookController::class, 'handleAggregator']);

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
        Route::post('/exam', [PurchaseController::class, 'buyExam']);
        Route::post('/streaming', [PurchaseController::class, 'buyStreaming']);
        Route::post('/verify', [PurchaseController::class, 'verify']);
    });

    Route::prefix('service-requests')->group(function () {
        Route::get('/', [ServiceRequestController::class, 'list']);
        Route::post('/airtime-to-cash', [ServiceRequestController::class, 'submitAirtimeToCash']);
        Route::post('/bulk', [ServiceRequestController::class, 'submitBulk']);
        Route::get('/{id}', [ServiceRequestController::class, 'show']);
        Route::post('/{id}/cancel', [ServiceRequestController::class, 'cancel']);
    });

    Route::get('/services', [ServiceController::class, 'listServices']);
    Route::get('/services/{id}/products', [ServiceController::class, 'listProducts']);

    Route::get('/transactions', [TransactionController::class, 'history']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::get('/transactions/{id}/status', [TransactionController::class, 'status']);
});

Route::get('/settings/public', [SettingController::class, 'publicSettings']);

Route::middleware(['jwt.verify', 'admin'])->prefix('settings')->group(function () {
    Route::get('/', [SettingController::class, 'index']);
    Route::get('/{group}', [SettingController::class, 'show']);
    Route::get('/{group}/schema', [SettingController::class, 'schema']);
    Route::post('/{group}', [SettingController::class, 'update']);
});
