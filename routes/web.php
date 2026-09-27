<?php

use App\Http\Controllers\EmployeePairController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EmployeePairController::class, 'index'])->name('home');
Route::post('/', [EmployeePairController::class, 'analyze'])->name('employees.analyze');
