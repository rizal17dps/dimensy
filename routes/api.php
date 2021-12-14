<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', '\App\Http\Controllers\Api\AuthController@login');

Route::get('/v1/checkinfo/token', '\App\Http\Controllers\Api\AuthController@login');

//SingleDocument
Route::get('/getDocument', '\App\Http\Controllers\Api\SendController@getDocument');
Route::post('/sendDocument', '\App\Http\Controllers\Api\SendController@sendDocument');
Route::post('/signing', '\App\Http\Controllers\Api\SendController@signing');
Route::post('/getOtp', '\App\Http\Controllers\Api\SendController@getOtp');
Route::post('/download', '\App\Http\Controllers\Api\SendController@download');

//BulkUploadDok
Route::post('/sendBulkDocument', '\App\Http\Controllers\Api\SendBulkController@sendDocument');
Route::post('/getOtpBulk', '\App\Http\Controllers\Api\SendBulkController@getOtp');
Route::post('/signingBulk', '\App\Http\Controllers\Api\SendBulkController@signing');

//Register User
Route::post('/register', '\App\Http\Controllers\Api\RegisterController@registerUser');

//cek quota
Route::get('/quota', '\App\Http\Controllers\Api\QuotaController@cekQuota');

//Meterai
Route::get('/DocType', '\App\Http\Controllers\Api\MeteraiController@jenisDok');
Route::post('/meteraiSigning', '\App\Http\Controllers\Api\MeteraiController@signingMeterai');
