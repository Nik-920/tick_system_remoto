<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LocationController;
use App\Http\Controllers\Web\QrScanController;
use App\Http\Controllers\Web\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthController::class)->name('health.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::get('/scan/{token}', [QrScanController::class, 'show'])
        ->middleware('throttle:20,1')
        ->name('scan.show');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('/tickets/{ticket}/state', [TicketController::class, 'updateState'])
        ->middleware('throttle:5,1')
        ->name('tickets.update-state');

    Route::middleware('role:admin|super_admin')->group(function (): void {
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::get('/locations/create', [LocationController::class, 'create'])->name('locations.create');
        Route::post('/locations', [LocationController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('locations.store');
        Route::get('/locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
        Route::patch('/locations/{location}', [LocationController::class, 'update'])
            ->middleware('throttle:5,1')
            ->name('locations.update');
        Route::post('/locations/{location}/regenerate-qr', [LocationController::class, 'regenerateQr'])
            ->middleware('throttle:5,1')
            ->name('locations.regenerate-qr');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])
            ->middleware('throttle:5,1')
            ->name('categories.update');
    });
});
