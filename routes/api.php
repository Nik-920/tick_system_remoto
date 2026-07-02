<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ResendWebhookController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Webhook de Resend: sin auth:sanctum (Resend no puede autenticarse como
// usuario), protegido en cambio por verificación de firma Svix dentro del
// propio controller (fail-closed).
Route::post('/webhooks/resend', ResendWebhookController::class)->name('api.webhooks.resend');

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/locations', [LocationController::class, 'index'])->name('api.locations.index');
    Route::get('/locations/{location}', [LocationController::class, 'show'])->name('api.locations.show');
    Route::post('/locations', [LocationController::class, 'store'])
        ->middleware(['idempotency', 'throttle:creations'])
        ->name('api.locations.store');
    Route::patch('/locations/{location}', [LocationController::class, 'update'])
        ->middleware(['idempotency', 'throttle:mutations'])
        ->name('api.locations.update');
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])
        ->middleware(['idempotency', 'throttle:mutations'])
        ->name('api.locations.destroy');
    Route::post('/locations/{location}/regenerate-qr', [LocationController::class, 'regenerateQr'])
        ->middleware(['idempotency', 'throttle:mutations'])
        ->name('api.locations.regenerate-qr');

    Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('api.categories.show');
    Route::post('/categories', [CategoryController::class, 'store'])
        ->middleware(['idempotency', 'throttle:creations'])
        ->name('api.categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])
        ->middleware(['idempotency', 'throttle:mutations'])
        ->name('api.categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
        ->middleware(['idempotency', 'throttle:mutations'])
        ->name('api.categories.destroy');

    $ticketMutationMiddleware = ['idempotency', 'throttle:mutations'];
    $ticketAdminMutationMiddleware = ['role:admin|super_admin', ...$ticketMutationMiddleware];

    Route::get('/tickets', [TicketController::class, 'index'])->name('api.tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])
        ->middleware(['idempotency', 'throttle:creations'])
        ->name('api.tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('api.tickets.show');
    Route::patch('/tickets/{ticket}/claim', [TicketController::class, 'claim'])
        ->middleware($ticketMutationMiddleware)
        ->name('api.tickets.claim');
    Route::patch('/tickets/{ticket}/release', [TicketController::class, 'release'])
        ->middleware($ticketMutationMiddleware)
        ->name('api.tickets.release');
    Route::patch('/tickets/{ticket}/assign', [TicketController::class, 'assign'])
        ->middleware($ticketAdminMutationMiddleware)
        ->name('api.tickets.assign');
    Route::patch('/tickets/{ticket}/unassign', [TicketController::class, 'unassign'])
        ->middleware($ticketAdminMutationMiddleware)
        ->name('api.tickets.unassign');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])
        ->middleware($ticketMutationMiddleware)
        ->name('api.tickets.destroy');
    Route::patch('/tickets/{ticket}/state', [TicketController::class, 'updateState'])
        ->middleware($ticketMutationMiddleware)
        ->name('api.tickets.update-state');
    Route::patch('/tickets/{ticket}/duplicate-review', [TicketController::class, 'reviewDuplicate'])
        ->middleware($ticketMutationMiddleware)
        ->name('api.tickets.duplicate-review.update');

    // Gestión de usuarios/roles: reservada a super_admin.
    // Defensa en profundidad: middleware role:super_admin como respaldo de las
    // policies (UserPolicy), alineado con las rutas Web equivalentes.
    Route::middleware('role:super_admin')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('api.users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('api.users.show');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware(['idempotency', 'throttle:creations'])
            ->name('api.users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('api.users.update');
        Route::post('/users/{user}/avatar', [UserController::class, 'updateAvatar'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('api.users.update-avatar');
        Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('api.users.update-role');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('api.users.destroy');
    });
});
