<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FcmTokenController;
use App\Http\Controllers\Web\LocationController;
use App\Http\Controllers\Web\MaintenanceDashboardReportController;
use App\Http\Controllers\Web\MaintenanceDashboardV2Controller;
use App\Http\Controllers\Web\MetricsController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\QrScanController;
use App\Http\Controllers\Web\ReporterCommunityController;
use App\Http\Controllers\Web\ReporterDashboardController;
use App\Http\Controllers\Web\ReporterGuideController;
use App\Http\Controllers\Web\ReporterTicketController;
use App\Http\Controllers\Web\TicketAssignmentsController;
use App\Http\Controllers\Web\TicketController;
use App\Http\Controllers\Web\TicketHistoryController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthController::class)->name('health.show');

// Métricas operativas: exponen conteos de usuarios/roles y datos agregados.
// Requieren sesión autenticada con rol admin/super_admin (no es endpoint público).
// Si se necesita scraping por Prometheus, usar un middleware de token/IP-allowlist.
Route::middleware(['auth', 'role:admin|super_admin'])
    ->get('/metrics', MetricsController::class)
    ->name('metrics');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:auth-sensitive')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:auth-sensitive')
        ->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('/fcm-tokens', [FcmTokenController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('fcm.store');
    Route::delete('/fcm-tokens', [FcmTokenController::class, 'destroy'])
        ->middleware('throttle:10,1')
        ->name('fcm.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.readAll');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Self-service maintenance report (HTML preview + PDF) for the date range.
    // Scoped to the maintenance role: admin/super_admin have their own analytics
    // and must not export another technician's report from here.
    Route::middleware('role:maintenance')->group(function (): void {
        Route::get('/dashboard/maintenance/report', [MaintenanceDashboardReportController::class, 'preview'])
            ->name('dashboard.maintenance.report');
        Route::get('/dashboard/maintenance/report.pdf', [MaintenanceDashboardReportController::class, 'pdf'])
            ->name('dashboard.maintenance.report.pdf');

        // Dashboard Maintenance V2 — parallel redesign (static visual phase).
        // Does NOT replace /dashboard; the PDF button reuses the report.pdf route above.
        Route::get('/dashboard/maintenance-v2', MaintenanceDashboardV2Controller::class)
            ->name('dashboard.maintenance-v2');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:mutations')
        ->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])
        ->middleware('throttle:mutations')
        ->name('profile.update-avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])
        ->middleware('throttle:mutations')
        ->name('profile.delete-avatar');

    Route::get('/scan/{token}', [QrScanController::class, 'show'])
        ->middleware('throttle:20,1')
        ->name('scan.show');

    $ticketMutationMiddleware = ['idempotency', 'throttle:mutations'];
    $ticketAdminMutationMiddleware = ['role:admin|super_admin', ...$ticketMutationMiddleware];

    // Reporter home dashboard — redesigned personal panel (static visual phase).
    // Parallel to /dashboard (which keeps rendering the live dashboard.reporter
    // view); this is the surface the sidebar "Dashboard" links to for reporters.
    Route::get('/reporter/dashboard', ReporterDashboardController::class)
        ->middleware('role:reporter')
        ->name('reporter.dashboard');

    // "Guía del reporter" — static help page (destination of the "Ver guía"
    // links across the reporter surfaces).
    Route::get('/reporter/guide', ReporterGuideController::class)
        ->middleware('role:reporter')
        ->name('reporter.guide');

    // "Comunidad del campus" — reporter-only social feed (skeleton v1).
    // Phase 1: static shimmer skeleton, no live data. Future phases will
    // connect CommunityFeedQuery + reactions/saves/comments.
    Route::get('/reporter/community', ReporterCommunityController::class)
        ->middleware('role:reporter')
        ->name('reporter.community');

    // "Mis tickets" — reporter-only board + per-ticket tracking (static visual
    // phase, no live data yet). Parallel to the classic /tickets list, which
    // stays intact for live data; this is the redesigned reporter surface the
    // sidebar links to for reporters.
    Route::middleware('role:reporter')
        ->prefix('reporter/tickets')
        ->name('reporter.tickets.')
        ->group(function () use ($ticketMutationMiddleware): void {
            Route::get('/', [ReporterTicketController::class, 'index'])->name('index');
            // "Historial" — literal segment registered before /{ticket} so it is
            // not captured as a ticket id.
            Route::get('/history', [ReporterTicketController::class, 'history'])->name('history');
            // Edit ONE own request. The controller resolves it inside the
            // reporter_id boundary (404 otherwise) and re-checks editability via
            // TicketPolicy@update (own + open + unassigned + unlocked). The
            // literal /edit segment is registered before /{ticket} for clarity.
            Route::get('/{ticket}/edit', [ReporterTicketController::class, 'edit'])->name('edit');
            Route::patch('/{ticket}', [ReporterTicketController::class, 'update'])
                ->middleware($ticketMutationMiddleware)
                ->name('update');
            // "Cancelar solicitud" — voluntary withdrawal (open → cancelled). Same
            // ownership boundary + cancellation window (own + open + unassigned +
            // unlocked) as edit. NOT a rejection and NOT a delete.
            Route::patch('/{ticket}/cancel', [ReporterTicketController::class, 'cancel'])
                ->middleware($ticketMutationMiddleware)
                ->name('cancel');
            Route::get('/{ticket}', [ReporterTicketController::class, 'show'])->name('show');
        });

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/available', [TicketController::class, 'available'])
        ->middleware('role:maintenance|admin|super_admin')
        ->name('tickets.available');
    // "Mis asignaciones" — maintenance-only board (static visual phase, no live data yet).
    // Registered before /tickets/{ticket} so the literal segment is not captured as a model.
    Route::get('/tickets/assignments', TicketAssignmentsController::class)
        ->middleware('role:maintenance')
        ->name('tickets.assignments');
    // "Historial" — maintenance-only history board (static visual phase, no live data yet).
    // Registered before /tickets/{ticket} so the literal segment is not captured as a model.
    Route::get('/tickets/history', TicketHistoryController::class)
        ->middleware('role:maintenance')
        ->name('tickets.history');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])
        ->middleware(['idempotency', 'throttle:creations'])
        ->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('/tickets/{ticket}/claim', [TicketController::class, 'claim'])
        ->middleware($ticketMutationMiddleware)
        ->name('tickets.claim');
    Route::patch('/tickets/{ticket}/release', [TicketController::class, 'release'])
        ->middleware($ticketMutationMiddleware)
        ->name('tickets.release');
    Route::patch('/tickets/{ticket}/assign', [TicketController::class, 'assign'])
        ->middleware($ticketAdminMutationMiddleware)
        ->name('tickets.assign');
    Route::patch('/tickets/{ticket}/unassign', [TicketController::class, 'unassign'])
        ->middleware($ticketAdminMutationMiddleware)
        ->name('tickets.unassign');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])
        ->middleware($ticketMutationMiddleware)
        ->name('tickets.destroy');
    // Edición limitada operativa desde tickets.show (maintenance asignado o
    // admin/super_admin): corrige categoría/prioridad y adjunta evidencias.
    // NO toca título/descripción/reporter — eso vive en reporter.tickets.update.
    Route::patch('/tickets/{ticket}/maintenance', [TicketController::class, 'updateMaintenance'])
        ->middleware($ticketMutationMiddleware)
        ->name('tickets.maintenance.update');
    Route::patch('/tickets/{ticket}/state', [TicketController::class, 'updateState'])
        ->middleware($ticketMutationMiddleware)
        ->name('tickets.update-state');
    Route::patch('/tickets/{ticket}/duplicate-review', [TicketController::class, 'reviewDuplicate'])
        ->middleware($ticketMutationMiddleware)
        ->name('tickets.duplicate-review.update');

    Route::middleware('role:admin|super_admin')->group(function (): void {
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::get('/locations/create', [LocationController::class, 'create'])->name('locations.create');
        Route::post('/locations', [LocationController::class, 'store'])
            ->middleware(['idempotency', 'throttle:creations'])
            ->name('locations.store');
        Route::get('/locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
        Route::patch('/locations/{location}', [LocationController::class, 'update'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('locations.update');
        Route::delete('/locations/{location}', [LocationController::class, 'destroy'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('locations.destroy');
        Route::post('/locations/{location}/regenerate-qr', [LocationController::class, 'regenerateQr'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('locations.regenerate-qr');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])
            ->middleware(['idempotency', 'throttle:creations'])
            ->name('categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('categories.destroy');
    });

    Route::middleware('role:super_admin')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware(['idempotency', 'throttle:creations'])
            ->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('users.update');
        Route::post('/users/{user}/avatar', [UserController::class, 'updateAvatar'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('users.update-avatar');
        Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('users.update-role');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware(['idempotency', 'throttle:mutations'])
            ->name('users.destroy');
    });
});
