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
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OutboxController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Planner;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrStatusController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierProductController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Vendor;
use App\Http\Controllers\WeddingGalleryController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
| Public portfolio of past weddings ("Our Weddings")
*/
Route::get('/weddings', [WeddingGalleryController::class, 'index'])->name('weddings.index');
Route::get('/weddings/photos/{photo}', [WeddingGalleryController::class, 'photo'])->name('weddings.photo');
Route::get('/weddings/{entry:slug}', [WeddingGalleryController::class, 'show'])->name('weddings.show');

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
    // Accounts the Admin creates: the person sets their own password from the emailed link.
    Route::get('/set-password/{token}', [Auth\PasswordSetupController::class, 'edit'])->name('password.setup');
    Route::post('/set-password', [Auth\PasswordSetupController::class, 'update'])->middleware('throttle:10,1')->name('password.setup.store');
});

Route::post('/logout', [Auth\LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Email verification: the signed link works without logging in (e.g. opened on a phone).
Route::get('/email/verify/{id}/{hash}', [Auth\EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->whereNumber('id')->name('verification.verify');
Route::post('/email/verification-notification', [Auth\EmailVerificationController::class, 'send'])
    ->middleware(['auth', 'throttle:3,1'])->name('verification.send');

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
    Route::put('/account/couple', [ProfileController::class, 'updateCouple'])->name('account.couple');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

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
        Route::post('/users/{user}/invite', [Admin\UserController::class, 'invite'])->middleware('throttle:10,1')->name('users.invite');

        // Wedding Planners and Suppliers who signed up themselves
        Route::patch('/users/{user}/approve', [Admin\RegistrationController::class, 'approve'])->name('users.approve');
        Route::delete('/users/{user}/reject', [Admin\RegistrationController::class, 'reject'])->name('users.reject');

        // Chatbot knowledge base
        Route::get('/faqs/logs', [Admin\FaqController::class, 'logs'])->name('faqs.logs');
        Route::resource('faqs', Admin\FaqController::class)->except('show');

        // Nudge a planner about overdue tasks and past weddings left open
        Route::post('/planners/{user}/remind', Admin\PlannerReminderController::class)->middleware('throttle:10,1')->name('planners.remind');

        // Planners ask to void a payment; only the admin can void it or keep it.
        Route::patch('/payments/{payment}/void', [Admin\PaymentVoidController::class, 'approve'])->name('payments.void');
        Route::patch('/payments/{payment}/keep', [Admin\PaymentVoidController::class, 'keep'])->name('payments.keep');
    });

    // Test-mode email outbox (only while MAIL_MAILER=log). Admin only: it holds password reset links and one-time PINs.
    Route::middleware('role:admin')->group(function () {
        Route::get('/outbox', [OutboxController::class, 'index'])->name('outbox.index');
        Route::get('/outbox/{message}', [OutboxController::class, 'show'])->name('outbox.show');
    });

    /*
    | Wedding Planner operations
    */
    Route::middleware('role:planner')->group(function () {
        Route::get('/planner', Planner\DashboardController::class)->name('planner.dashboard');

        // Booking Management (UC-03)
        Route::resource('bookings', BookingController::class)->except('destroy');
        Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::patch('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
        Route::patch('/bookings/{booking}/complete', [BookingController::class, 'complete'])->name('bookings.complete');

        // Supplier selection + QR code (UC-04)
        Route::post('/bookings/{booking}/suppliers', [BookingSupplierController::class, 'store'])->name('bookings.suppliers.store');
        Route::patch('/bookings/{booking}/suppliers/{assignment}', [BookingSupplierController::class, 'update'])->name('bookings.suppliers.update');
        Route::delete('/bookings/{booking}/suppliers/{assignment}', [BookingSupplierController::class, 'destroy'])->name('bookings.suppliers.destroy');
        Route::post('/bookings/{booking}/suppliers/{assignment}/send', [BookingSupplierController::class, 'send'])->name('bookings.suppliers.send');
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

        // Meetings couples asked for
        Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
        Route::patch('/meetings/{meeting}', [MeetingController::class, 'respond'])->name('meetings.respond');

        // Tasks (UC-05)
        Route::resource('tasks', TaskController::class)->except('show');
        Route::patch('/tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');

        // Inventory (UC-06)
        Route::resource('inventory', InventoryController::class)->except('show')->parameters(['inventory' => 'item']);
        Route::post('/inventory/{item}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

        // Payments (UC-08)
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        // A planner can't remove money they recorded: they ask the admin to void it.
        Route::post('/payments/{payment}/void-request', [PaymentController::class, 'requestVoid'])->name('payments.void-request');
        Route::get('/bookings/{booking}/contract.pdf', [PaymentController::class, 'contract'])->name('bookings.contract');

        // Public portfolio management
        Route::resource('portfolio', PortfolioController::class)->except('show');
        Route::patch('/portfolio/{portfolio}/photos/{photo}/cover', [PortfolioController::class, 'cover'])->name('portfolio.photos.cover');
        Route::delete('/portfolio/{portfolio}/photos/{photo}', [PortfolioController::class, 'destroyPhoto'])->name('portfolio.photos.destroy');
    });

    /*
    | Reports (UC-09): the admin sees every report (read-only), planners the operational ones.
    */
    Route::middleware('role:admin,planner')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    });

    /*
    | Supplier catalog as couples see it; planners may open it as a preview.
    */
    Route::middleware('role:client,planner')->prefix('my')->name('client.')->group(function () {
        Route::get('/catalog', [Client\CatalogController::class, 'index'])->name('catalog');
        Route::get('/catalog/{supplier}', [Client\CatalogController::class, 'show'])->name('catalog.show');
    });

    /*
    | Couple-Client
    */
    Route::middleware('role:client')->prefix('my')->name('client.')->group(function () {
        Route::get('/', Client\DashboardController::class)->name('dashboard');
        Route::get('/status/{booking}', [Client\DashboardController::class, 'status'])->name('status');
        Route::get('/qr/{booking}', [Client\DashboardController::class, 'qr'])->name('qr');
        Route::get('/wedding-date', [Client\WeddingDateController::class, 'edit'])->name('wedding-date');
        Route::put('/wedding-date', [Client\WeddingDateController::class, 'update'])->name('wedding-date.update');
        Route::get('/wedding-date/availability', [Client\WeddingDateController::class, 'availability'])->middleware('throttle:30,1')->name('wedding-date.availability');
        Route::post('/booking-request', [Client\BookingRequestController::class, 'store'])->middleware('throttle:10,1')->name('booking-request');
        Route::get('/meetings', [Client\MeetingController::class, 'index'])->name('meetings');
        Route::post('/meetings', [Client\MeetingController::class, 'store'])->middleware('throttle:10,1')->name('meetings.store');
        Route::patch('/meetings/{meeting}/cancel', [Client\MeetingController::class, 'cancel'])->name('meetings.cancel');
        Route::get('/planner', [Client\PlannerChoiceController::class, 'edit'])->name('planner');
        Route::put('/planner', [Client\PlannerChoiceController::class, 'update'])->name('planner.update');
        Route::get('/budget', [Client\BudgetController::class, 'edit'])->name('budget');
        Route::put('/budget', [Client\BudgetController::class, 'update'])->name('budget.update');
        Route::get('/package', [Client\PackageController::class, 'edit'])->name('package');
        Route::put('/package', [Client\PackageController::class, 'update'])->name('package.update');
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
        Route::post('/bookings/{booking}/respond', [Vendor\DashboardController::class, 'respond'])->name('bookings.respond');
        Route::get('/profile', [Vendor\DashboardController::class, 'profile'])->name('profile');
        Route::patch('/availability', [Vendor\DashboardController::class, 'availability'])->name('availability');
        Route::resource('products', SupplierProductController::class)->except('show');
    });
});
