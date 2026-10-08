<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingProductController;
use App\Http\Controllers\BookingSupplierController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\Client;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OutboxController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Planner;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrStatusController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierProductController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Vendor;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
| Authentication (UC-01)
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [Auth\LoginController::class, 'show'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', [Auth\RegisterController::class, 'show'])->name('register');
    Route::post('/register', [Auth\RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [Auth\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [Auth\PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [Auth\PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [Auth\PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/logout', [Auth\LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
| QR Code Status Tracker with OTP verification (UC-10) — no login required
*/
Route::prefix('status/{token}')->name('qr.')->where(['token' => '[a-f0-9]{64}'])->group(function () {
    Route::get('/', [QrStatusController::class, 'show'])->middleware('throttle:30,1')->name('show');
    Route::post('/send', [QrStatusController::class, 'send'])->middleware('throttle:3,1')->name('send');
    Route::post('/verify', [QrStatusController::class, 'verify'])->middleware('throttle:10,1')->name('verify');
    Route::get('/view', [QrStatusController::class, 'status'])->name('status');
});

/*
| Chatbot / FAQ assistant (UC-11) — guests allowed, sessions log user_id when signed in
*/
Route::get('/chatbot/faqs', [ChatbotController::class, 'faqs'])->name('chatbot.faqs');
Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])->middleware('throttle:20,1')->name('chatbot.ask');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->dashboardRoute()))->name('dashboard');
    Route::get('/account', [ProfileController::class, 'edit'])->name('account.edit');
    Route::get('/products/{product}/image', [SupplierProductController::class, 'image'])->withTrashed()->name('products.image');
    Route::put('/account/password', [ProfileController::class, 'updatePassword'])->name('account.password');

    /*
    | Admin
    */
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        // User Management (UC-02)
        Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
        Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->withTrashed()->name('users.edit');
        Route::put('/users/{user}', [Admin\UserController::class, 'update'])->withTrashed()->name('users.update');
        Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/users/{user}/restore', [Admin\UserController::class, 'restore'])->withTrashed()->name('users.restore');
        Route::patch('/users/{user}/unlock', [Admin\UserController::class, 'unlock'])->name('users.unlock');

        // Chatbot knowledge base
        Route::get('/faqs/logs', [Admin\FaqController::class, 'logs'])->name('faqs.logs');
        Route::resource('faqs', Admin\FaqController::class)->except('show');
    });

    /*
    | Admin + Wedding Planner operations
    */
    Route::middleware('role:admin,planner')->group(function () {
        Route::get('/planner', Planner\DashboardController::class)->middleware('role:planner')->name('planner.dashboard');

        // Booking Management (UC-03)
        Route::resource('bookings', BookingController::class)->except('destroy');
        Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::patch('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
        Route::patch('/bookings/{booking}/complete', [BookingController::class, 'complete'])->name('bookings.complete');

        // Supplier selection + QR code (UC-04)
        Route::post('/bookings/{booking}/suppliers', [BookingSupplierController::class, 'store'])->name('bookings.suppliers.store');
        Route::patch('/bookings/{booking}/suppliers/{assignment}', [BookingSupplierController::class, 'update'])->name('bookings.suppliers.update');
        Route::delete('/bookings/{booking}/suppliers/{assignment}', [BookingSupplierController::class, 'destroy'])->name('bookings.suppliers.destroy');
        Route::get('/bookings/{booking}/qr', [BookingController::class, 'qr'])->name('bookings.qr');
        Route::get('/bookings/{booking}/qr.svg', [BookingController::class, 'qrDownload'])->name('bookings.qr.download');

        // Supplier Catalog management (UC-07)
        Route::resource('suppliers', SupplierController::class)->except('show');
        Route::patch('/suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->withTrashed()->name('suppliers.restore');
        Route::resource('suppliers.products', SupplierProductController::class)->except('show');

        // Vendor products chosen for a wedding's set-up
        Route::post('/bookings/{booking}/items', [BookingProductController::class, 'store'])->name('bookings.items.store');
        Route::patch('/bookings/{booking}/items/{item}', [BookingProductController::class, 'update'])->name('bookings.items.update');
        Route::delete('/bookings/{booking}/items/{item}', [BookingProductController::class, 'destroy'])->name('bookings.items.destroy');

        // Tasks (UC-05)
        Route::resource('tasks', TaskController::class)->except('show');
        Route::patch('/tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');

        // Inventory (UC-06)
        Route::resource('inventory', InventoryController::class)->except('show')->parameters(['inventory' => 'item']);
        Route::post('/inventory/{item}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

        // Payments (UC-08)
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
        Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
        Route::get('/bookings/{booking}/contract.pdf', [PaymentController::class, 'contract'])->name('bookings.contract');

        // Test-mode email outbox (only while MAIL_MAILER=log)
        Route::get('/outbox', [OutboxController::class, 'index'])->name('outbox.index');
        Route::get('/outbox/{message}', [OutboxController::class, 'show'])->name('outbox.show');

        // Reports (UC-09)
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    });

    /*
    | Supplier catalog as couples see it; staff may open it as a preview.
    */
    Route::middleware('role:client,admin,planner')->prefix('my')->name('client.')->group(function () {
        Route::get('/catalog', [Client\CatalogController::class, 'index'])->name('catalog');
        Route::get('/catalog/{supplier}', [Client\CatalogController::class, 'show'])->name('catalog.show');
    });

    /*
    | Couple-Client
    */
    Route::middleware('role:client')->prefix('my')->name('client.')->group(function () {
        Route::get('/', Client\DashboardController::class)->name('dashboard');
        Route::get('/status/{booking}', [Client\DashboardController::class, 'status'])->name('status');
        Route::post('/catalog/preferences', [Client\CatalogController::class, 'store'])->name('preferences.store');
        Route::delete('/catalog/preferences/{preference}', [Client\CatalogController::class, 'destroy'])->name('preferences.destroy');
    });

    /*
    | Vendor / Supplier
    */
    Route::middleware('role:vendor')->prefix('vendor')->name('vendor.')->group(function () {
        Route::get('/', [Vendor\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/bookings', [Vendor\DashboardController::class, 'bookings'])->name('bookings');
        Route::get('/bookings/{booking}', [Vendor\DashboardController::class, 'booking'])->name('bookings.show');
        Route::get('/profile', [Vendor\DashboardController::class, 'profile'])->name('profile');
        Route::patch('/availability', [Vendor\DashboardController::class, 'availability'])->name('availability');
        Route::resource('products', SupplierProductController::class)->except('show');
    });
});
