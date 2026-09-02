<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

// Fuera de 'auth': el fondo tambien se cambia desde la pantalla de ingreso.
Route::post('/tema', [ThemeController::class, 'toggle'])->name('theme.toggle');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    // Con redirect() a secas sale "/proyectos" pelado y se pierde la subcarpeta
    // cuando el panel no cuelga de la raiz del dominio (XAMPP, /goharv-panel/public).
    Route::get('/', fn () => redirect()->route('projects.index'));

    Route::get('/proyectos', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/proyectos/nuevo', [ProjectController::class, 'create'])->name('projects.create');
    // Antes de /proyectos/{project}, o el slug se come la palabra "archivados".
    Route::get('/proyectos/archivados', [ProjectController::class, 'archived'])->name('projects.archived');
    Route::post('/proyectos', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/proyectos/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/proyectos/{project}/editar', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/proyectos/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('/proyectos/{project}/estado', [ProjectController::class, 'moveStatus'])->name('projects.status');
    Route::post('/proyectos/{project}/comentarios', [ProjectController::class, 'comment'])
        ->middleware('throttle:30,1')
        ->name('projects.comment');
    Route::delete('/proyectos/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::patch('/proyectos/{project}/restaurar', [ProjectController::class, 'restore'])
        ->withTrashed()
        ->name('projects.restore');

    // Borrado definitivo: pantalla de confirmacion aparte, porque no se deshace.
    Route::get('/proyectos/{project}/eliminar', [ProjectController::class, 'confirmDelete'])
        ->withTrashed()
        ->name('projects.delete.confirm');
    Route::delete('/proyectos/{project}/definitivo', [ProjectController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('projects.force-destroy');

    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    // Pide la clave actual: sin limite, una sesion ajena podria adivinarla.
    Route::put('/perfil/clave', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('profile.password');

    Route::middleware('admin')->group(function () {
        Route::get('/equipo', [MemberController::class, 'index'])->name('members.index');
        Route::post('/equipo', [MemberController::class, 'store'])->name('members.store');
        Route::patch('/equipo/{user}', [MemberController::class, 'update'])->name('members.update');
    });
});
