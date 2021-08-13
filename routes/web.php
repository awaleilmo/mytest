<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::group(['middleware' => 'web'], function () {
    Route::get('api/documentation', '\L5Swagger\Http\Controllers\SwaggerController@api')->name('l5swagger.api');
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

//api
Route::post('/api/login', [App\Http\Controllers\Controller::class, 'login'])->name('api/login');
Route::post('/api/logout', [App\Http\Controllers\Controller::class, 'singout'])->name('api/logout');
Route::post('/api/register', [App\Http\Controllers\Controller::class, 'register'])->name('api/register');
Route::post('/api/balance/post', [App\Http\Controllers\Controller::class, 'postbalance'])->name('api/postbalance');
Route::post('/api/product/post', [App\Http\Controllers\Controller::class, 'postProduct'])->name('api/postproduct');
Route::get('/api/order/get', [App\Http\Controllers\Controller::class, 'detailorder'])->name('api/getorder');
Route::Post('/api/pay', [App\Http\Controllers\Controller::class, 'payorder'])->name('api/pay');
Route::get('/api/history', [App\Http\Controllers\Controller::class, 'historyorder'])->name('api/history');




