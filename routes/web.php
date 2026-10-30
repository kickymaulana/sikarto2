<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstrumentController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::get('/auth/sso', [AuthController::class, 'redirectSso'])->name('sso.redirect')->middleware('guest');
Route::get('/callback', [AuthController::class, 'callbackSso'])->name('sso.callback')->middleware('guest');
Route::get('/pending-role', [AuthController::class, 'pendingRole'])->name('pending-role')->middleware('guest');
Route::post('/pending-role', [AuthController::class, 'submitRole'])->name('pending-role.submit')->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Kelola user (approval manual provisioning) — super_admin only
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index')
        ->middleware('permission:user.manage');
    Route::post('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve')
        ->middleware('permission:user.manage');
    Route::post('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role')
        ->middleware('permission:user.manage');
    Route::post('/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('users.deactivate')
        ->middleware('permission:user.manage');

    // Master data (generic, guard by permission)
    Route::get('/masters', fn () => redirect()->route('masters.index', ['entity' => 'factories']))
        ->middleware('permission:master.read');
    Route::get('/masters/matrix', fn () => redirect()->route('laporan.matrix'))
        ->middleware('permission:master.read');
    Route::get('/masters/matrix/export', fn () => redirect()->route('laporan.matrix.export'))
        ->middleware('permission:master.read');
    Route::get('/laporan/matrix', [MasterController::class, 'matrix'])->name('laporan.matrix')
        ->middleware('permission:master.read|report.read');
    Route::get('/laporan/matrix/export', [MasterController::class, 'matrixExport'])->name('laporan.matrix.export')
        ->middleware('permission:master.read|report.read');
    Route::get('/masters/{entity}/create', [MasterController::class, 'create'])->name('masters.create')
        ->middleware('permission:master.create');
    Route::get('/masters/{entity}/{id}/edit', [MasterController::class, 'edit'])->name('masters.edit')
        ->middleware('permission:master.update');
    Route::get('/masters/{entity}', [MasterController::class, 'index'])->name('masters.index')
        ->middleware('permission:master.read');
    Route::post('/masters/{entity}', [MasterController::class, 'store'])->name('masters.store')
        ->middleware('permission:master.create');
    Route::put('/masters/{entity}/{id}', [MasterController::class, 'update'])->name('masters.update')
        ->middleware('permission:master.update');
    Route::delete('/masters/{entity}/{id}', [MasterController::class, 'destroy'])->name('masters.destroy')
        ->middleware('permission:master.delete');

    // Instruments (full CRUD)
    Route::post('/instruments/{instrument}/activate', [InstrumentController::class, 'activate'])
        ->name('instruments.activate')
        ->withTrashed()
        ->middleware('permission:master.update');
    Route::resource('instruments', InstrumentController::class)
        ->withTrashed()
        ->middleware('permission:master.read|master.create|master.update|master.delete');

    // Pengujian
    Route::get('/tests', [TestController::class, 'index'])->name('tests.index')
        ->middleware('permission:test.read');
    Route::get('/tests/create', [TestController::class, 'create'])->name('tests.create')
        ->middleware('permission:test.create');
    Route::post('/tests', [TestController::class, 'store'])->name('tests.store')
        ->middleware('permission:test.create');
    Route::get('/tests/{test}', [TestController::class, 'show'])->name('tests.show')
        ->middleware('permission:test.read');

    // Laporan
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')
        ->middleware('permission:report.read');
    Route::get('/laporan/internal-kalibrasi', [ReportController::class, 'internalKalibrasi'])->name('laporan.internal-kalibrasi')
        ->middleware('permission:report.read');
    Route::get('/laporan/internal-kalibrasi/export', [ReportController::class, 'internalKalibrasiExport'])->name('laporan.internal-kalibrasi.export')
        ->middleware('permission:report.export');
    Route::get('/laporan', fn () => redirect()->route('laporan.matrix'))->name('laporan.index')
        ->middleware('permission:master.read|report.read');
});
