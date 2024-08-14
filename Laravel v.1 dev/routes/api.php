<?php

use App\Http\Controllers\API\OrderAPIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthApiController;
use App\Http\Controllers\API\CartAPIController;

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

Route::post('register', [AuthApiController::class, 'register']);
Route::post('login', [AuthApiController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::get('carts', [CartAPIController::class, 'index']);
    Route::post('carts', [CartAPIController::class, 'store']);
    Route::get('carts/{id}', [CartAPIController::class, 'show']);
    Route::put('carts/{id}', [CartAPIController::class, 'update']);
    Route::delete('carts/{id}', [CartAPIController::class, 'destroy']);

    Route::get('/orders', [OrderAPIController::class, 'index']);
    Route::post('checkout', [OrderAPIController::class, 'checkout']);
    Route::get('orders/{id}', [OrderAPIController::class, 'show']);

    Route::post('logout', [AuthApiController::class, 'logout'])->name('auth.logout');
    Route::post('refresh', [AuthApiController::class, 'refresh'])->name('auth.refresh');
    Route::post('me', [AuthApiController::class, 'me'])->name('auth.me');
});