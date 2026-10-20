<?php

use App\Http\Controllers\AdvisorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerCareAdminController;
use App\Http\Controllers\CustomerCareController;
use App\Http\Controllers\CustomerPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StoreExtensionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/tai-khoan/quen-mat-khau', [CustomerPasswordController::class, 'form'])->name('account.password.request');
Route::post('/tai-khoan/quen-mat-khau', [CustomerPasswordController::class, 'send'])->middleware('throttle:5,1')->name('account.password.email');
Route::get('/tai-khoan/dat-lai-mat-khau/{token}', [CustomerPasswordController::class, 'resetForm'])->name('account.password.reset.form');
Route::post('/tai-khoan/dat-lai-mat-khau', [CustomerPasswordController::class, 'reset'])->middleware('throttle:10,1')->name('account.password.reset');
Route::middleware('customer')->group(function () {
    Route::post('/tai-khoan/danh-gia/{product}', [CustomerCareController::class, 'review'])->middleware('throttle:10,1')->name('account.review');
    Route::get('/tai-khoan/don-hang/{order}/ho-tro', [CustomerCareController::class, 'form'])->name('account.support');
    Route::post('/tai-khoan/don-hang/{order}/ho-tro', [CustomerCareController::class, 'store'])->middleware('throttle:5,1')->name('account.support.store');
});
Route::prefix('admin/customer-care')->middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/requests', [CustomerCareAdminController::class, 'requests'])->name('care.requests');
    Route::put('/requests/{ticket}', [CustomerCareAdminController::class, 'respond'])->name('care.requests.respond');
    Route::middleware('role:admin')->group(function () {
        Route::get('/reviews', [CustomerCareAdminController::class, 'reviews'])->name('care.reviews');
        Route::put('/reviews/{review}', [CustomerCareAdminController::class, 'moderate'])->name('care.reviews.moderate');
        Route::get('/messages', [CustomerCareAdminController::class, 'messages'])->name('care.messages');
        Route::post('/messages/{message}/retry', [CustomerCareAdminController::class, 'retry'])->name('care.messages.retry');
    });
});

Route::get('/', [ShopController::class, 'index'])->name('shop.index');
Route::get('/san-pham', [ShopController::class, 'index'])->name('shop.catalog');
Route::get('/thuong-hieu', [ShopController::class, 'brands'])->name('shop.brands');
Route::get('/tu-van', [AdvisorController::class, 'history'])->middleware('throttle:60,1')->name('advisor.history');
Route::post('/tu-van', [AdvisorController::class, 'message'])->middleware('throttle:12,1')->name('advisor.message');
Route::delete('/tu-van', [AdvisorController::class, 'clear'])->middleware('throttle:30,1')->name('advisor.clear');
Route::get('/san-pham/{product}', [ShopController::class, 'show'])->name('shop.product');
Route::get('/hinh-anh/{filename}', [ShopController::class, 'media'])->name('media');
Route::get('/gio-hang', [ShopController::class, 'cart'])->name('shop.cart');
Route::post('/gio-hang/uu-dai', [ShopController::class, 'coupon'])->name('shop.coupon');
Route::get('/huong-dan', [ShopController::class, 'guide'])->name('shop.guide');
Route::get('/tai-khoan/dang-nhap', [CustomerAccountController::class, 'form'])->name('account.login');
Route::post('/tai-khoan/dang-nhap', [CustomerAccountController::class, 'login'])->middleware('throttle:15,1')->name('account.login.submit');
Route::get('/tai-khoan/dang-ky', [CustomerAccountController::class, 'form'])->name('account.register');
Route::post('/tai-khoan/dang-ky', [CustomerAccountController::class, 'register'])->middleware('throttle:10,1')->name('account.register.submit');
Route::prefix('tai-khoan')->middleware('customer')->group(function () {
    Route::get('/', [CustomerAccountController::class, 'index'])->name('account.index');
    Route::put('/ho-so', [CustomerAccountController::class, 'profile'])->name('account.profile');
    Route::put('/mat-khau', [CustomerAccountController::class, 'password'])->name('account.password');
    Route::post('/dang-xuat', [CustomerAccountController::class, 'logout'])->name('account.logout');
    Route::post('/yeu-thich/{product}', [CustomerAccountController::class, 'wishlist'])->name('account.wishlist');
    Route::delete('/yeu-thich/{product}', [CustomerAccountController::class, 'removeWishlist'])->name('account.wishlist.remove');
    Route::post('/don-hang/{order}/huy', [CustomerAccountController::class, 'cancel'])->name('account.cancel');
});
Route::post('/gio-hang', [ShopController::class, 'add'])->name('shop.cart.add');
Route::patch('/gio-hang/{variant}', [ShopController::class, 'update'])->name('shop.cart.update');
Route::post('/dat-hang', [ShopController::class, 'checkout'])->middleware('throttle:20,1')->name('shop.checkout');
Route::get('/don-hang/{token}', [ShopController::class, 'order'])->name('shop.order');
Route::get('/don-hang/{token}/thanh-toan', [ShopController::class, 'payment'])->name('shop.payment');
Route::post('/don-hang/{token}/bao-chuyen-khoan', [ShopController::class, 'reportTransfer'])->middleware('throttle:5,1')->name('shop.transfer.report');
Route::post('/don-hang/{token}/huy', [ShopController::class, 'cancel'])->middleware('throttle:5,1')->name('shop.cancel');
Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [AuthController::class, 'form'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])->name('login.submit');
});
Route::post('/dang-xuat', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::prefix('admin')->middleware(['auth', 'role:admin,staff'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/doi-mat-khau', [AuthController::class, 'passwordForm'])->name('password.form');
    Route::put('/doi-mat-khau', [AuthController::class, 'password'])->name('password.update');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/inventory', [DashboardController::class, 'inventory'])->name('inventory');
    Route::get('/customers/{customer}', [MasterDataController::class, 'customer'])->name('customers.show');
    Route::get('/data/{resource}', [MasterDataController::class, 'index'])->name('data.index');
    Route::post('/data/{resource}', [MasterDataController::class, 'save'])->name('data.store');
    Route::put('/data/{resource}/{record}', [MasterDataController::class, 'save'])->name('data.update');
    Route::delete('/data/{resource}/{record}', [MasterDataController::class, 'destroy'])->name('data.destroy');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/complete', [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/verify-transfer', [OrderController::class, 'verifyTransfer'])->name('orders.transfer.verify');
    Route::post('/orders/{order}/shipment', [OrderController::class, 'shipment'])->name('orders.shipment');
    Route::post('/orders/{order}/delivery', [StoreExtensionController::class, 'delivery'])->name('orders.delivery');
    Route::middleware('role:admin')->group(function () {
        Route::post('/orders/{order}/refund-cancelled', [OrderController::class, 'refundCancelled'])->name('orders.refund.cancelled');
        Route::get('/coupons', [StoreExtensionController::class, 'coupons'])->name('coupons.index');
        Route::post('/coupons', [StoreExtensionController::class, 'saveCoupon'])->name('coupons.store');
        Route::put('/coupons/{coupon}', [StoreExtensionController::class, 'saveCoupon'])->name('coupons.update');
        Route::get('/adjustments', [StoreExtensionController::class, 'adjustmentForm'])->name('adjustments.index');
        Route::post('/adjustments', [StoreExtensionController::class, 'adjust'])->name('adjustments.store');
        Route::get('/returns', [StoreExtensionController::class, 'returns'])->name('returns.index');
        Route::post('/orders/{order}/return', [StoreExtensionController::class, 'returnOrder'])->name('orders.return');
        Route::get('/export/{type}', [StoreExtensionController::class, 'export'])->name('export');
        Route::get('/products/create', [ProductController::class, 'form'])->name('products.create');
        Route::post('/products', [ProductController::class, 'save'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'form'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'save'])->name('products.update');
        Route::post('/products/{product}/images/{image}', [ProductController::class, 'image'])->name('products.image');
        Route::post('/products/{product}/variants', [ProductController::class, 'variant'])->name('variants.store');
        Route::put('/products/{product}/variants/{variant}', [ProductController::class, 'variant'])->name('variants.update');
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::post('/purchases/{purchase}/confirm', [PurchaseController::class, 'confirm'])->name('purchases.confirm');
        Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'save'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'save'])->name('users.update');
    });
});
