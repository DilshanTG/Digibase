<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Development Login Route
if (app()->environment('local', 'testing')) {
    Route::get('/dev/login/{id}', function ($id) {
        auth()->loginUsingId($id);
        return redirect('/admin');
    })->name('dev.login');
}
