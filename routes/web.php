<?php

use App\Http\Controllers\Api\SdkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// JavaScript SDK
Route::get('/sdk/digibase.js', [SdkController::class, 'generate'])->name('sdk.js');

// Development Login Route
if (app()->environment('local', 'testing')) {
    Route::get('/dev/login/{id}', function ($id) {
        auth()->loginUsingId($id);
        return redirect('/admin');
    })->name('dev.login');
}
