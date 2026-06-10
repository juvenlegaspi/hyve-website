<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::get('/bookings/availability', [BookingController::class, 'availability'])->name('bookings.availability');
Route::get('/bookings/unavailable-dates', [BookingController::class, 'unavailableDates'])->name('bookings.unavailable-dates');
Route::get('/bookings/room-layout', [BookingController::class, 'roomLayout'])->name('bookings.room-layout');
Route::get('/bookings/quote', [BookingController::class, 'quote'])->name('bookings.quote');
Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
