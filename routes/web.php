<?php

use App\Http\Controllers\Auth\CustomerGoogleAuthController;
use App\Services\BusinessAuthService;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('explore', 'pages::explore.index')->name('explore');

Route::livewire('b/{slug}', 'pages::storefront.show')->name('storefront.show');

Route::middleware(['auth:web,customer,business'])->group(function () {
    Route::get('dashboard', function () {
        if (Auth::guard('business')->check()) {
            return redirect()->route('business.dashboard', request()->query());
        }

        if (Auth::guard('customer')->check()) {
            return redirect()->route('customer.tickets', request()->query());
        }

        return view('dashboard');
    })->name('dashboard');
});

Route::middleware('guest:customer')->group(function () {
    Route::get('auth/google/redirect', [CustomerGoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');

    Route::get('auth/google/callback', [CustomerGoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::livewire('login', 'pages::auth.customer.login')
    ->middleware('guest:customer')
    ->name('login');

Route::livewire('customer/login', 'pages::auth.customer.login')
    ->middleware('guest:customer')
    ->name('customer.login');

Route::livewire('register', 'pages::auth.customer.register')
    ->middleware('guest')
    ->name('register');

Route::livewire('customer/register', 'pages::auth.customer.register')
    ->middleware('guest')
    ->name('customer.register');

Route::livewire('tickets/{code}', 'pages::tickets.show')->name('tickets.show');

Route::livewire('business/login', 'pages::auth.business.login')
    ->middleware('guest:business,web')
    ->name('business.login');

Route::livewire('business/register', 'pages::auth.business.register')
    ->middleware('guest:business,web')
    ->name('business.register');

Route::post('business/logout', function (BusinessAuthService $businessAuthService) {
    $businessAuthService->logout();

    return redirect()->route('business.login');
})->name('business.logout');

Route::post('customer/logout', function () {
    Auth::guard('customer')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('customer.login');
})->name('customer.logout');

Route::middleware('auth:customer')->group(function () {
    Route::livewire('my-tickets', 'pages::customer.tickets')->name('customer.tickets');
});

Route::middleware(['auth:business'])->group(function () {
    Route::view('business/dashboard', 'pages.business.dashboard')->name('business.dashboard');
    Route::livewire('business/queue', 'pages::business.queue.index')->name('business.queue.index');
    Route::get('business/live-queue', function () {
        return redirect()->route('business.queue.index');
    });
    Route::livewire('business/offerings', 'pages::business.offerings.index')->name('business.offerings.index');
    Route::livewire('business/offerings/create', 'pages::business.offerings.create')->name('business.offerings.create');
    Route::livewire('business/offerings/{offering}/edit', 'pages::business.offerings.edit')->name('business.offerings.edit');
});

require __DIR__.'/settings.php';
