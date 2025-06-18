<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Registration\RegisterController;
use App\Http\Controllers\Registration\LoginController;
use App\Http\Controllers\ForgetPasswordController;
use App\Http\Controllers\userController;
use App\Http\Controllers\CarsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});



Route::middleware('auth:sanctum')->group(function () {
    Route::get('Get_my_info', [userController::class, 'Get_my_info']);
    Route::get('get_all_mycars', [CarsController::class, 'get_all_mycars']);
    Route::post('update_my_info/{id}', [userController::class, 'update_my_info']);
    Route::post('storeCar', [CarsController::class, 'storeCar']);
    Route::post('updateCar/{id}', [CarsController::class, 'updateCar']);
    Route::post('updateCarFeatures/{id}', [CarsController::class, 'updateCarFeatures']);
    Route::delete('deleteCar/{id}', [CarsController::class, 'destroy']);
});


Route::get('test', function (Request $request) {
    return 'test';
});




Route::post('register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/forget-password', [ForgetPasswordController::class, 'forgetPassword']);
Route::post('/reset-password', [ForgetPasswordController::class, 'resetPasswordByVerifyOtp']);

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');


/*
Route::group(['middleware' => ['web']], function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);
});
*/


/*Route::group(['middleware' => ['web']], function () {
    Route::get('auth/facebook/redirect', [FacebookController::class, 'redirect']);
    Route::get('auth/facebook/callback', [FacebookController::class, 'callback']);
});
*/
