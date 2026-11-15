<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use App\Http\Controllers\Storefront\CategoryController as StorefrontCategoryController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\OrderController as StorefrontOrderController;
use App\Http\Controllers\Storefront\PaymentProofController;
use App\Http\Controllers\Storefront\TrackingController;
use App\Http\Controllers\Storefront\CustomerAuthController;
use App\Http\Controllers\Admin\StoreContextController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\AttributeDefinitionController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\AuthController;

// Health
Route::get('/health', HealthController::class);

// Public storefront (ResolvePublicStore middleware applied in bootstrap)
Route::prefix('storefront')->middleware(['resolve.public.store'])->group(function () {
    Route::get('/layout', [\App\Http\Controllers\Storefront\LayoutController::class, 'show']);
    Route::get('/payment-info', [\App\Http\Controllers\Storefront\LayoutController::class, 'paymentInfo']);
    Route::get('/categories', [StorefrontCategoryController::class, 'index']);
    Route::get('/products', [StorefrontProductController::class, 'index']);
    Route::get('/search', [StorefrontProductController::class, 'index']);
    Route::get('/products/{slug}', [StorefrontProductController::class, 'show']);

    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{itemId}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);

    Route::post('/orders', [StorefrontOrderController::class, 'store']);
    Route::post('/orders/{publicId}/notify-invoice', [StorefrontOrderController::class, 'notifyInvoice']);
    Route::get('/orders/{publicId}', [StorefrontOrderController::class, 'show']);
    Route::post('/orders/{publicId}/payment-proofs', [PaymentProofController::class, 'store']);

    Route::get('/tracking/{token}', [TrackingController::class, 'byToken']);
    Route::post('/tracking/lookup', [TrackingController::class, 'byCode']);

    Route::post('/auth/register', [CustomerAuthController::class, 'register']);
    Route::post('/auth/login', [CustomerAuthController::class, 'login']);
});

// Admin (auth + store context middleware applied in bootstrap)
Route::post("/admin/login", [AuthController::class, "login"]);
Route::prefix('admin')->middleware(['admin.store'])->group(function () {
    Route::get('/stores', [StoreContextController::class, 'index']);
    Route::post('/store-context', [StoreContextController::class, 'set']);
    Route::get('/store-context', [StoreContextController::class, 'current']);

    Route::apiResource('products', AdminProductController::class);
    Route::post('/products/{product}/publish', [AdminProductController::class, 'publish']);
    Route::post('/products/{product}/archive', [AdminProductController::class, 'archive']);

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('product-attributes', AttributeDefinitionController::class);

    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/{variant}', [InventoryController::class, 'show']);
    Route::post('/inventory/{variant}/adjustments', [InventoryController::class, 'adjust']);
    Route::get('/inventory/{variant}/movements', [InventoryController::class, 'movements']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::get('/payments/{payment}/proofs/{proof}', [PaymentController::class, 'proof']);
    Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify']);
    Route::post('/orders/{order}/mark-paid', [PaymentController::class, 'markPaid']);
    Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject']);
    Route::get('/orders', function () {
        $store = app(\App\Services\StoreContext::class)->get();
        return \App\Models\Order::where('store_id', $store->id)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->paginate(25);
    });


    Route::post('/orders/{order}/preparing', [DeliveryController::class, 'preparing']);
    Route::post('/orders/{order}/shipped', [DeliveryController::class, 'shipped']);
    Route::post('/orders/{order}/delivered', [DeliveryController::class, 'delivered']);

    Route::get('/imports/template', [ImportController::class, 'template']);
    Route::post('/imports', [ImportController::class, 'store']);
    Route::get('/imports/{job}', [ImportController::class, 'show']);
    Route::get('/imports/{job}/preview', [ImportController::class, 'preview']);
    Route::post('/imports/{job}/confirm', [ImportController::class, 'confirm']);

    Route::get('/settings', [SettingsController::class, 'index']);
    Route::patch('/settings', [SettingsController::class, 'update']);
    Route::get('/settings/delivery', [SettingsController::class, 'delivery']);
    Route::patch('/settings/delivery', [SettingsController::class, 'updateDelivery']);
    Route::get('/settings/payment', [SettingsController::class, 'payment']);
    Route::patch('/settings/payment', [SettingsController::class, 'updatePayment']);
});
