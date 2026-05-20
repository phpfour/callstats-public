<?php

use App\Http\Controllers\Backoffice\CallLogController;
use App\Http\Controllers\Backoffice\DashboardController;
use App\Http\Controllers\Backoffice\LeadController;
use App\Http\Controllers\Backoffice\LeadImportController;
use App\Http\Controllers\Backoffice\ReportController;
use App\Http\Controllers\Backoffice\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'role:admin|supervisor'])
    ->prefix('backoffice')
    ->name('backoffice.')
    ->group(function () {
        Route::redirect('/', '/backoffice/dashboard');

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/create', [LeadController::class, 'create'])->name('leads.create');
        Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
        Route::post('leads/bulk-assign', [LeadController::class, 'bulkAssign'])->name('leads.bulk-assign');
        Route::post('leads/import', [LeadImportController::class, 'store'])->name('leads.import');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
        Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');

        Route::get('call-logs', [CallLogController::class, 'index'])->name('call-logs.index');
        Route::get('call-logs/export', [CallLogController::class, 'export'])->name('call-logs.export');
        Route::get('call-logs/{callLog}', [CallLogController::class, 'show'])->name('call-logs.show');
        Route::get('call-logs/{callLog}/edit', [CallLogController::class, 'edit'])->name('call-logs.edit');
        Route::put('call-logs/{callLog}', [CallLogController::class, 'update'])->name('call-logs.update');

        Route::get('reports/agent-performance', [ReportController::class, 'agentPerformance'])->name('reports.agent-performance');
        Route::get('reports/agent-performance/export', [ReportController::class, 'exportAgentPerformance'])->name('reports.agent-performance.export');

        Route::middleware('role:admin')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });

        require __DIR__.'/settings.php';
    });
