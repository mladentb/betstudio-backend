<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SportController;
use App\Http\Controllers\Api\LeagueController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\PriceController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminCompanyController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\LeaguePriceController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\MyGamesController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AdminStreamingController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\SolanaPaymentController;
use App\Http\Controllers\Api\NFTController;
use App\Http\Controllers\Api\PublicApiController;
use App\Http\Controllers\Api\AdminApiKeyController;
use App\Http\Controllers\Api\AdminDashboardController;

/*
|--------------------------------------------------------------------------
| API Routes - BetStudio
|--------------------------------------------------------------------------
*/

// ============================================
// AUTHENTICATION
// ============================================
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
        Route::get('team', [AuthController::class, 'getTeamMembers']);
        Route::post('team', [AuthController::class, 'addTeamMember']);
        Route::delete('team/{member}', [AuthController::class, 'removeTeamMember']);
    });
});

// ============================================
// CART & MY GAMES
// ============================================
Route::middleware('auth:sanctum')->group(function () {
    // Cart
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart', [CartController::class, 'add']);
    Route::delete('cart/{gameId}', [CartController::class, 'remove']);
    Route::delete('cart', [CartController::class, 'clear']);
    Route::post('cart/checkout', [CartController::class, 'checkout']);

    // My purchased games
    Route::get('my-games', [MyGamesController::class, 'index']);
    Route::get('my-games/{purchasedGame}', [MyGamesController::class, 'show']);
    Route::put('my-games/{purchasedGame}/stream', [MyGamesController::class, 'updateStreamSettings']);

    // Invoices
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download']);
    Route::get('invoices/{invoice}/preview', [InvoiceController::class, 'preview']);

    // Solana Payments
    Route::post('orders/create-pending', [SolanaPaymentController::class, 'createPendingOrder']);
    Route::post('orders/{order}/confirm-solana', [SolanaPaymentController::class, 'confirmPayment']);
    Route::get('solana/tx-status', [SolanaPaymentController::class, 'getTransactionStatus']);

    // NFT Access Passes
    Route::get('nfts/my', [NFTController::class, 'myNFTs']);
    Route::put('nfts/{purchasedGame}/update', [NFTController::class, 'updateNFTInfo']);
    Route::post('nfts/{purchasedGame}/verify-access', [NFTController::class, 'verifyAccess']);
    Route::post('nfts/{purchasedGame}/transfer', [NFTController::class, 'recordTransfer']);

    // User Orders
    Route::get('my-orders/{order}', [SolanaPaymentController::class, 'getOrder']);
});

// Public stats (no auth)
Route::get('stats/{token}', [MyGamesController::class, 'publicStats']);

// Currency (public read, auth for update)
Route::get('currencies', [CurrencyController::class, 'index']);
Route::post('currencies/convert', [CurrencyController::class, 'convert']);
Route::put('user/currency', [AuthController::class, 'updateCurrency'])->middleware('auth:sanctum');
Route::put('user/wallet', [AuthController::class, 'updateWallet'])->middleware('auth:sanctum');

// ============================================
// ADMIN ROUTES
// ============================================
Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    // Dashboard Stats
    Route::get('dashboard/stats', [AdminDashboardController::class, 'stats']);

    // User management
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/pending-count', [AdminUserController::class, 'pendingCount']);
    Route::get('users/{user}', [AdminUserController::class, 'show']);
    Route::put('users/{user}', [AdminUserController::class, 'update']);
    Route::delete('users/{user}', [AdminUserController::class, 'destroy']);
    Route::post('users/{user}/approve', [AdminUserController::class, 'approve']);
    Route::post('users/{user}/reject', [AdminUserController::class, 'reject']);

    // Admin Companies (for invoicing)
    Route::get('companies', [AdminCompanyController::class, 'index']);
    Route::post('companies', [AdminCompanyController::class, 'store']);
    Route::get('companies/{company}', [AdminCompanyController::class, 'show']);
    Route::get('companies/{company}/users', [AdminCompanyController::class, 'users']);
    Route::put('companies/{company}', [AdminCompanyController::class, 'update']);
    Route::delete('companies/{company}', [AdminCompanyController::class, 'destroy']);
    Route::post('companies/{company}/logo', [AdminCompanyController::class, 'uploadLogo']);
    Route::delete('companies/{company}/logo', [AdminCompanyController::class, 'deleteLogo']);

    // Orders & Finance
    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/statistics', [AdminOrderController::class, 'statistics']);
    Route::get('orders/{order}', [AdminOrderController::class, 'show']);
    Route::put('orders/{order}/payment', [AdminOrderController::class, 'updatePaymentStatus']);
    Route::post('orders/{order}/invoice', [AdminOrderController::class, 'generateInvoice']);

    // Admin Invoices
    Route::get('invoices', [InvoiceController::class, 'adminIndex']);
    Route::get('invoices/{invoice}/download', [InvoiceController::class, 'adminDownload']);

    // Analytics
    Route::get('analytics', [AnalyticsController::class, 'dashboard']);

    // Streaming Destinations
    Route::get('streaming', [AdminStreamingController::class, 'index']);

    // League Pricing
    Route::get('prices', [LeaguePriceController::class, 'index']);
    Route::put('prices/{league}', [LeaguePriceController::class, 'update']);
    Route::post('prices/bulk', [LeaguePriceController::class, 'bulkUpdate']);

    // API Keys Management
    Route::get('api-keys', [AdminApiKeyController::class, 'index']);
    Route::post('api-keys', [AdminApiKeyController::class, 'store']);
    Route::get('api-keys/analytics', [AdminApiKeyController::class, 'analytics']);
    Route::get('api-keys/{apiKey}', [AdminApiKeyController::class, 'show']);
    Route::put('api-keys/{apiKey}', [AdminApiKeyController::class, 'update']);
    Route::delete('api-keys/{apiKey}', [AdminApiKeyController::class, 'destroy']);
    Route::post('api-keys/{apiKey}/reset', [AdminApiKeyController::class, 'resetCounter']);
    Route::post('api-keys/{apiKey}/regenerate', [AdminApiKeyController::class, 'regenerate']);
    Route::get('api-keys/{apiKey}/logs', [AdminApiKeyController::class, 'logs']);
    
    // Webhooks Management
    Route::get('api-keys/{apiKey}/webhooks', [AdminApiKeyController::class, 'webhooks']);
    Route::post('api-keys/{apiKey}/webhooks', [AdminApiKeyController::class, 'createWebhook']);
    Route::put('webhooks/{webhook}', [AdminApiKeyController::class, 'updateWebhook']);
    Route::delete('webhooks/{webhook}', [AdminApiKeyController::class, 'deleteWebhook']);
    Route::post('webhooks/{webhook}/test', [AdminApiKeyController::class, 'testWebhook']);
    Route::get('webhooks/{webhook}/logs', [AdminApiKeyController::class, 'webhookLogs']);
});

// ============================================
// SPORTS
// ============================================
Route::get('sports', [SportController::class, 'index']);
Route::get('sports/{sport}', [SportController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('sports', [SportController::class, 'store']);
    Route::put('sports/{sport}', [SportController::class, 'update']);
    Route::delete('sports/{sport}', [SportController::class, 'destroy']);
});

// ============================================
// LEAGUES
// ============================================
Route::get('leagues', [LeagueController::class, 'index']);
Route::get('leagues/{league}', [LeagueController::class, 'show']);
Route::get('sports/{sport}/leagues', [LeagueController::class, 'bySport']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('leagues', [LeagueController::class, 'store']);
    Route::put('leagues/{league}', [LeagueController::class, 'update']);
    Route::delete('leagues/{league}', [LeagueController::class, 'destroy']);
});

// ============================================
// GAMES
// ============================================
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('games', GameController::class);
    Route::get('games-upcoming', [GameController::class, 'upcoming']);
    Route::get('games-live', [GameController::class, 'live']);
    Route::get('games-today', [GameController::class, 'today']);
    Route::get('leagues/{league}/games', [GameController::class, 'byLeague']);
});

// ============================================
// PRICES
// ============================================
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('prices', PriceController::class);
    Route::get('games/{game}/price', [PriceController::class, 'getGamePrice']);
});

// ============================================
// SYNC ENDPOINTS
// ============================================
Route::prefix('sync')->middleware('auth:sanctum')->group(function () {
    Route::post('sports', [SyncController::class, 'syncSports']);
    Route::post('leagues', [SyncController::class, 'syncLeagues']);
    Route::post('games/{league}', [SyncController::class, 'syncGames']);
    Route::post('all', [SyncController::class, 'syncAll']);
});

// ============================================
// API INFO
// ============================================
Route::get('/', function () {
    return response()->json([
        'name' => 'BetStudio API',
        'version' => '1.0.0',
    ]);
});

// ============================================
// PUBLIC API (v1) - Za eksterne klijente
// ============================================
Route::prefix('v1')->middleware('api.key')->group(function () {
    // Sports
    Route::get('sports', [PublicApiController::class, 'sports']);
    
    // Leagues
    Route::get('leagues', [PublicApiController::class, 'leagues']);
    
    // Games
    Route::get('games', [PublicApiController::class, 'games']);
    Route::get('games/live', [PublicApiController::class, 'liveGames']);
    Route::get('games/today', [PublicApiController::class, 'todayGames']);
    Route::get('games/upcoming', [PublicApiController::class, 'upcomingGames']);
    Route::get('games/{id}', [PublicApiController::class, 'game']);
    
    // Statistics
    Route::get('stats/overview', [PublicApiController::class, 'statsOverview']);
});
