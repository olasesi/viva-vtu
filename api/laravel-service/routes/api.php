<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDisputeController;
use App\Http\Controllers\AdminKnowledgeBaseController;
use App\Http\Controllers\AdminTicketController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\ResellerHierarchyController;
use App\Http\Controllers\ResellerWebhookController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionPinController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/paystack', [WebhookController::class, 'handlePaystack']);
Route::post('/webhook/flutterwave', [WebhookController::class, 'handleFlutterwave']);
Route::post('/webhook/aggregator/{slug}', [WebhookController::class, 'handleAggregator']);

Route::prefix('kb')->group(function () {
    Route::get('/categories', [KnowledgeBaseController::class, 'categories']);
    Route::get('/articles', [KnowledgeBaseController::class, 'articles']);
    Route::get('/articles/{slug}', [KnowledgeBaseController::class, 'show']);
});

Route::middleware('jwt.verify')->group(function () {

    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'getBalance']);
        Route::post('/fund', [WalletController::class, 'fund']);
        Route::get('/verify/{reference}', [WalletController::class, 'verify']);
        Route::get('/history', [WalletController::class, 'history']);
        Route::post('/transfer', [WalletController::class, 'transfer'])->middleware('transaction.pin');
    });

    Route::prefix('purchase')->middleware('transaction.pin')->group(function () {
        Route::post('/airtime', [PurchaseController::class, 'buyAirtime']);
        Route::post('/data', [PurchaseController::class, 'buyData']);
        Route::post('/electricity', [PurchaseController::class, 'buyElectricity']);
        Route::post('/cable', [PurchaseController::class, 'buyCable']);
        Route::post('/exam', [PurchaseController::class, 'buyExam']);
        Route::post('/streaming', [PurchaseController::class, 'buyStreaming']);
    });

    Route::prefix('purchase')->group(function () {
        Route::post('/verify', [PurchaseController::class, 'verify']);
        Route::post('/verify-phone', [PurchaseController::class, 'verifyPhone']);
    });

    Route::get('/transaction-pin', [TransactionPinController::class, 'status']);
    Route::post('/transaction-pin', [TransactionPinController::class, 'set']);
    Route::post('/transaction-pin/change', [TransactionPinController::class, 'change']);
    Route::post('/transaction-pin/remove', [TransactionPinController::class, 'remove']);

    Route::prefix('service-requests')->group(function () {
        Route::get('/', [ServiceRequestController::class, 'list']);
        Route::post('/airtime-to-cash', [ServiceRequestController::class, 'submitAirtimeToCash']);
        Route::post('/bulk', [ServiceRequestController::class, 'submitBulk']);
        Route::get('/{id}', [ServiceRequestController::class, 'show']);
        Route::post('/{id}/cancel', [ServiceRequestController::class, 'cancel']);
    });

    Route::get('/services', [ServiceController::class, 'listServices']);
    Route::get('/services/{id}/products', [ServiceController::class, 'listProducts']);

    Route::get('/referral', [ReferralController::class, 'status']);

    Route::get('/kyc', [KycController::class, 'status']);
    Route::post('/kyc', [KycController::class, 'submit']);

    Route::get('/reseller/webhooks', [ResellerWebhookController::class, 'index']);
    Route::post('/reseller/webhooks', [ResellerWebhookController::class, 'store']);
    Route::delete('/reseller/webhooks/{id}', [ResellerWebhookController::class, 'destroy']);
    Route::get('/reseller/hierarchy', [ResellerHierarchyController::class, 'tree']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::post('/tickets/{id}/reply', [TicketController::class, 'reply']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::get('/disputes', [DisputeController::class, 'index']);
    Route::post('/disputes', [DisputeController::class, 'store']);
    Route::get('/disputes/{id}', [DisputeController::class, 'show']);

    Route::get('/beneficiaries', [BeneficiaryController::class, 'index']);
    Route::post('/beneficiaries', [BeneficiaryController::class, 'store']);
    Route::patch('/beneficiaries/{id}', [BeneficiaryController::class, 'update']);
    Route::delete('/beneficiaries/{id}', [BeneficiaryController::class, 'destroy']);
    Route::post('/beneficiaries/{id}/favorite', [BeneficiaryController::class, 'toggleFavorite']);

    Route::get('/transactions', [TransactionController::class, 'history']);
    Route::get('/transactions/status/{reference}', [TransactionController::class, 'statusByReference']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::get('/transactions/{id}/status', [TransactionController::class, 'status']);
});

Route::get('/settings/public', [SettingController::class, 'publicSettings']);

Route::middleware(['jwt.verify', 'admin', 'audit.admin'])->prefix('settings')->group(function () {
    Route::get('/', [SettingController::class, 'index']);
    Route::get('/{group}', [SettingController::class, 'show']);
    Route::get('/{group}/schema', [SettingController::class, 'schema']);
    Route::post('/{group}', [SettingController::class, 'update']);
});

Route::middleware(['jwt.verify', 'admin', 'audit.admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/transactions', [AdminController::class, 'transactions']);
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/aggregator-health', [AdminController::class, 'aggregatorHealth']);
    Route::post('/reseller-keys', [ResellerController::class, 'issueKey']);
    Route::delete('/reseller-keys/{id}', [ResellerController::class, 'revokeKey']);
    Route::post('/resellers/assign', [ResellerHierarchyController::class, 'assign']);
    Route::get('/tickets', [AdminTicketController::class, 'index']);
    Route::get('/tickets/{id}', [AdminTicketController::class, 'show']);
    Route::post('/tickets/{id}/reply', [AdminTicketController::class, 'reply']);
    Route::patch('/tickets/{id}', [AdminTicketController::class, 'update']);
    Route::get('/disputes', [AdminDisputeController::class, 'index']);
    Route::get('/disputes/{id}', [AdminDisputeController::class, 'show']);
    Route::patch('/disputes/{id}', [AdminDisputeController::class, 'update']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);
    Route::get('/knowledge-base/categories', [AdminKnowledgeBaseController::class, 'categories']);
    Route::post('/knowledge-base/categories', [AdminKnowledgeBaseController::class, 'storeCategory']);
    Route::patch('/knowledge-base/categories/{id}', [AdminKnowledgeBaseController::class, 'updateCategory']);
    Route::delete('/knowledge-base/categories/{id}', [AdminKnowledgeBaseController::class, 'deleteCategory']);
    Route::get('/knowledge-base/articles', [AdminKnowledgeBaseController::class, 'articles']);
    Route::get('/knowledge-base/articles/{id}', [AdminKnowledgeBaseController::class, 'showArticle']);
    Route::post('/knowledge-base/articles', [AdminKnowledgeBaseController::class, 'storeArticle']);
    Route::patch('/knowledge-base/articles/{id}', [AdminKnowledgeBaseController::class, 'updateArticle']);
    Route::delete('/knowledge-base/articles/{id}', [AdminKnowledgeBaseController::class, 'deleteArticle']);
});

Route::middleware('reseller')->prefix('v1')->group(function () {
    Route::get('/reseller/transactions', [ResellerController::class, 'status']);
    Route::post('/reseller/purchase/{category}', [ResellerController::class, 'purchase'])
        ->middleware('throttle:120,1');
});
