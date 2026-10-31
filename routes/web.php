<?php

use App\Http\Controllers\Auth\CustomerGoogleAuthController;
use App\Services\BusinessAuthService;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth:web,customer,business'])->group(function () {
    Route::get('dashboard', function () {
        if (Auth::guard('business')->check()) {
            return redirect()->route('business.dashboard', request()->query());
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

Route::livewire('register', 'pages::auth.customer.register')
    ->middleware('guest')
    ->name('register');

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

Route::middleware(['auth:business'])->group(function () {
    Route::view('business/dashboard', 'pages.business.dashboard')->name('business.dashboard');
    Route::view('business/offerings', 'pages.business.offerings.index')->name('business.offerings.index');
    Route::livewire('business/offerings/create', 'pages::business.offerings.create')->name('business.offerings.create');
});

require __DIR__.'/settings.php';
