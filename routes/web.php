<?php

use App\Http\Controllers\Auth\CustomerGoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth:web,customer,business'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
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

Route::livewire('business/register', 'pages::auth.business.register')
    ->middleware('guest:business,web')
    ->name('business.register');

require __DIR__.'/settings.php';
