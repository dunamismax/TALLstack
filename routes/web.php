<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('dashboard', 'pages::admin.dashboard')->name('dashboard');

    Route::livewire('admin/users', 'pages::admin.users')
        ->middleware('permission:manage-users')
        ->name('admin.users');

    Route::livewire('admin/roles', 'pages::admin.roles')
        ->middleware('permission:manage-roles')
        ->name('admin.roles');
});

require __DIR__.'/settings.php';
