<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;

use App\Http\Controllers\User\EmployeeController as UserEmployeeController;
use App\Http\Controllers\User\TrainingController as UserTrainingController;

use App\Http\Controllers\Admin\EmployeeController as AdminEmployeeController;
use App\Http\Controllers\Admin\TrainingController as AdminTrainingController;
use App\Http\Controllers\Admin\TrainingParticipantController as AdminTrainingParticipantController;
use App\Http\Controllers\Admin\EvaluationController as AdminEvaluationController;
use App\Http\Controllers\Admin\EmployeeStatisticController as AdminEmployeeStatisticController;

use App\Http\Controllers\DataController;

// Redirect default to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Login & Logout
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// User Routes
Route::prefix('user')
    ->name('user.')
    // ->middleware(['auth', 'role:user'])
    ->group(function () {

        // Employees
        Route::get('/employees', [UserEmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/{employee}', [UserEmployeeController::class, 'show'])->name('employees.show');

        // Trainings
        Route::get('/trainings', [UserTrainingController::class, 'index'])->name('trainings.index');
        Route::get('/trainings/{training}', [UserTrainingController::class, 'show'])->name('trainings.show');
    });

// Admin Routes
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {

        // Employees
        Route::prefix('employees')->name('employees.')->group(function () {
            Route::get('/', [AdminEmployeeController::class, 'index'])->name('index');
            Route::get('/create', [AdminEmployeeController::class, 'create'])->name('create');
            Route::post('/', [AdminEmployeeController::class, 'store'])->name('store');
            Route::get('/{employee}/detail', [AdminEmployeeController::class, 'show'])->name('show');
            Route::post('/import', [AdminEmployeeController::class, 'import'])->name('import');
            Route::get('/export', [AdminEmployeeController::class, 'export'])->name('export');
            Route::get('/download-template', [AdminEmployeeController::class, 'downloadTemplate'])->name('downloadTemplate');
            Route::get('/{employee}/edit', [AdminEmployeeController::class, 'edit'])->name('edit');
            Route::put('/{employee}', [AdminEmployeeController::class, 'update'])->name('update');
            Route::delete('/{employee}', [AdminEmployeeController::class, 'destroy'])->name('destroy');

            // Employee Statistics
            Route::prefix('statistics')->name('statistics.')->group(function () {
                Route::get('/', [AdminEmployeeStatisticController::class, 'index'])->name('index');
                Route::get('/create', [AdminEmployeeStatisticController::class, 'create'])->name('create');
                Route::post('/', [AdminEmployeeStatisticController::class, 'store'])->name('store');
                Route::get('/{statistic}/edit', [AdminEmployeeStatisticController::class, 'edit'])->name('edit');
                Route::put('/{statistic}', [AdminEmployeeStatisticController::class, 'update'])->name('update');
                Route::delete('/{statistic}', [AdminEmployeeStatisticController::class, 'destroy'])->name('destroy');
            });
        });

        // Trainings
        Route::prefix('trainings')->name('trainings.')->group(function () {
            Route::get('/', [AdminTrainingController::class, 'index'])->name('index');
            Route::get('/create', [AdminTrainingController::class, 'create'])->name('create');
            Route::post('/', [AdminTrainingController::class, 'store'])->name('store');
            Route::get('/{training}', [AdminTrainingController::class, 'show'])->name('show');
            Route::get('/{training}/edit', [AdminTrainingController::class, 'edit'])->name('edit');
            Route::put('/{training}', [AdminTrainingController::class, 'update'])->name('update');
            Route::delete('/{training}', [AdminTrainingController::class, 'destroy'])->name('destroy');
        });

        // Routes for training participants
        Route::prefix('trainings/{training}/participants')->name('trainings.participants.')->group(function () {
            Route::get('/create', [AdminTrainingParticipantController::class, 'create'])->name('create');
            Route::post('/', [AdminTrainingParticipantController::class, 'store'])->name('store');
            Route::get('/{employee}/edit', [AdminTrainingParticipantController::class, 'edit'])->name('edit');
            Route::put('/{employee}', [AdminTrainingParticipantController::class, 'update'])->name('update');
            Route::delete('/{employee}', [AdminTrainingParticipantController::class, 'destroy'])->name('destroy');
            Route::post('/{employee}/certificate', [AdminTrainingParticipantController::class, 'updateCertificate'])->name('updateCertificate');
        });

        // Evaluations
        Route::prefix('evaluations')->name('evaluations.')->group(function () {
            Route::get('/', [AdminEvaluationController::class, 'index'])->name('index');
            Route::get('/create/{nik?}', [AdminEvaluationController::class, 'create'])->name('create');
            Route::post('/store', [AdminEvaluationController::class, 'store'])->name('store');
            Route::get('/{evaluation}/detail', [AdminEvaluationController::class, 'show'])->name('show');
            Route::get('/{evaluation}/edit', [AdminEvaluationController::class, 'edit'])->name('edit');
            Route::put('/{evaluation}', [AdminEvaluationController::class, 'update'])->name('update');
            Route::delete('/{evaluation}', [AdminEvaluationController::class, 'destroy'])->name('destroy');
        });
    });

// Data Routes
Route::prefix('data')->name('data.')->group(function () {
    Route::get('/rkp', [DataController::class, 'rkp'])->name('rkp');
    Route::get('/bzting', [DataController::class, 'bzting'])->name('bzting');
    Route::get('/pelatihan', [DataController::class, 'pelatihan'])->name('pelatihan');
    Route::get('/ckp', [DataController::class, 'ckp'])->name('ckp');
    Route::get('/manajemen-talenta', [DataController::class, 'manajemenTalenta'])->name('manajemen-talenta');
});
