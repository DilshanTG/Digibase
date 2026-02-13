<?php

use App\Http\Controllers\Api\SdkController;
use App\Http\Controllers\AdminerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// 🗄️ Database Manager (Adminer)
Route::get('/admin/database-manager', [AdminerController::class, 'index'])->name('admin.database');


// JavaScript SDK
Route::get('/sdk/digibase.js', [SdkController::class, 'generate'])->name('sdk.js');

// 🛡️ Self-Healing Storage Link
Route::get('/fix-storage', function () {
    $target = storage_path('app/public');
    $link = public_path('storage');

    if (file_exists($link)) {
        return '✅ Storage link already exists.';
    }

    app('files')->link($target, $link);
    return '✅ Storage link created successfully!';
})->name('fix.storage');

// Development Login Route
if (app()->environment('local', 'testing')) {
    Route::get('/dev/login/{id}', function ($id) {
        auth()->loginUsingId($id);
        return redirect('/admin');
    })->name('dev.login');
}
