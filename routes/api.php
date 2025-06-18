<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Registration\RegisterController;
use App\Http\Controllers\Registration\LoginController;
use App\Http\Controllers\ForgetPasswordController;
use App\Http\Controllers\userController;
use App\Http\Controllers\CarsController;
use App\Http\Controllers\CarsFeaturesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrderBookingController;

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


    Route::prefix('profile')->group(function () {
        // Route::resource('profile', ProfileController::class);
        Route::post('store_profile', [ProfileController::class, 'store']);
        Route::post('update_my_profile', [ProfileController::class, 'update']);
        Route::get('get_my_profile', [ProfileController::class, 'index']);
    });


    Route::prefix('admin/profile')->group(function () {



        Route::get('get_my_profile', [ProfileController::class, 'get_my_profile']);
        Route::get('get_user_profile/{id}', [ProfileController::class, 'get_user_profile']);
    });


    Route::prefix('admin/user')->group(function () {
        Route::get('get_all', [userController::class, 'get_all']);

        Route::get('get_info/{id}', [userController::class, 'get_info']);
    });




    Route::get('Get_my_info', [userController::class, 'Get_my_info']);
    Route::get('get_all', [userController::class, 'get_all']);
    Route::post('update_my_info/{id}', [userController::class, 'update_my_info']);


    Route::get('get_all_mycars', [CarsController::class, 'get_all_mycars']);

    Route::delete('deleteCar/{id}', [CarsController::class, 'destroy']);

    Route::post('updateCarFeatures/{id}', [CarsController::class, 'updateCarFeatures']);
    Route::post('updateCar/{id}', [CarsController::class, 'updateCar']);

    Route::prefix('cars')->group(function () {

        Route::get('show_features/{id}', [CarsFeaturesController::class, 'show_features']);

        Route::post('storeCar', [CarsController::class, 'storeCar']);




        Route::get('index', [CarsController::class, 'index']);

        Route::post('filter', [CarsController::class, 'filterCars']);
    });

    Route::prefix('renter/cars/bookings')->group(function () {

        Route::post('store/{id}', [OrderBookingController::class, 'store']);
        Route::get('my_booking', [OrderBookingController::class, 'myBooking']);
        Route::get('my_order', [OrderBookingController::class, 'my_order']);
        Route::get('show/{id}', [OrderBookingController::class, 'show']);


        Route::prefix('my_order')->group(function () {

            Route::get('show/{id}', [OrderBookingController::class, 'show_my_order']);
            Route::post('update_status/{id}', [OrderBookingController::class, 'update_status']);
        });

    });
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
