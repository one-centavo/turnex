<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth:web,customer'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::livewire('login', 'pages::auth.customer.login')
    ->middleware('guest:customer')
    ->name('login');

Route::livewire('register', 'pages::auth.customer.register')
    ->middleware('guest')
    ->name('register');

require __DIR__.'/settings.php';
