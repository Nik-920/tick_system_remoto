<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/locations', [LocationController::class, 'index'])->name('api.locations.index');
    Route::get('/locations/{location}', [LocationController::class, 'show'])->name('api.locations.show');
    Route::post('/locations', [LocationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('api.locations.store');
    Route::patch('/locations/{location}', [LocationController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('api.locations.update');
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])
        ->middleware('throttle:5,1')
        ->name('api.locations.destroy');
    Route::post('/locations/{location}/regenerate-qr', [LocationController::class, 'regenerateQr'])
        ->middleware('throttle:5,1')
        ->name('api.locations.regenerate-qr');

    Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('api.categories.show');
    Route::post('/categories', [CategoryController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('api.categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('api.categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
        ->middleware('throttle:5,1')
        ->name('api.categories.destroy');

    Route::get('/tickets', [TicketController::class, 'index'])->name('api.tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('api.tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('api.tickets.show');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])
        ->middleware('throttle:5,1')
        ->name('api.tickets.destroy');
    Route::patch('/tickets/{ticket}/state', [TicketController::class, 'updateState'])
        ->middleware('throttle:5,1')
        ->name('api.tickets.update-state');

    Route::get('/users', [UserController::class, 'index'])->name('api.users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('api.users.show');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('api.users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('api.users.update');
    Route::post('/users/{user}/avatar', [UserController::class, 'updateAvatar'])
        ->middleware('throttle:5,1')
        ->name('api.users.update-avatar');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])
        ->middleware('throttle:5,1')
        ->name('api.users.update-role');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('throttle:5,1')
        ->name('api.users.destroy');
});
