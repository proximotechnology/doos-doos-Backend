<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentPlanController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('/pusher', function () {
    return view('pusher_react');
});


Route::get('/pusherprivate', function () {
    return view('pusher_private');
});



Route::get('api/payment/success', [PaymentPlanController::class, 'success'])->name('payment.success');
Route::get('api/payment/cancel', [PaymentPlanController::class, 'cancel'])->name('payment.cancel');
