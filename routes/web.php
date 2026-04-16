<?php

use App\Http\Controllers\Web\QrScanController;
use App\Http\Controllers\Web\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function (): void {
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
});
