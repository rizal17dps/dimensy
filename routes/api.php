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

//SingleDocument
Route::get('/getDocument/{id?}', '\App\Http\Controllers\Api\SendController@getDocument');
Route::post('/download', '\App\Http\Controllers\Api\SendController@download');
Route::get('/getFile/{id}', '\App\Http\Controllers\Api\SendController@downloadPath');

//cek quota
Route::get('/quota', '\App\Http\Controllers\Api\QuotaController@cekQuota');
Route::post('/cekSingleQuota', '\App\Http\Controllers\Api\QuotaController@cekSingleQuota');
Route::post('/historyTrans', '\App\Http\Controllers\Api\QuotaController@historyTrans');
Route::post('/transfer', '\App\Http\Controllers\Api\QuotaController@transfer');
Route::get('/monitor', '\App\Http\Controllers\Api\QuotaController@monitor');
Route::get('/cekDokGagal', '\App\Http\Controllers\Api\QuotaController@cekDokGagal');
Route::get('/insertStatus/{id}', '\App\Http\Controllers\Api\QuotaController@insertStatus');


//Meterai
Route::get('/DocType', '\App\Http\Controllers\Api\MeteraiController@jenisDok');
Route::post('/meteraiSigning', '\App\Http\Controllers\Api\MeteraiController@signingMeterai');
Route::post('/insertQuota', '\App\Http\Controllers\Api\MeteraiController@insertQuota');
Route::post('/generateSN', '\App\Http\Controllers\Api\MeteraiController@generateSN');
Route::get('/cekDokSN/{id?}', '\App\Http\Controllers\Api\MeteraiController@cekDokSN');
Route::get('/cekSN/{id}', '\App\Http\Controllers\Api\MeteraiController@cekSN');
Route::get('/cekSNgagal', '\App\Http\Controllers\Api\MeteraiController@cekSNgagal');
Route::get('/cekGagalStamp', '\App\Http\Controllers\Api\QuotaController@cekGagalStamp');
Route::get('/updateStamp', '\App\Http\Controllers\Api\MeteraiController@updateStamp');
Route::post('/updateStamp', '\App\Http\Controllers\Api\MeteraiController@updateDok');

//pengembalian meterai & quota
Route::post('/invalidSerialNumber', '\App\Http\Controllers\Api\QuotaController@invalidSerialNumber');
Route::post('/cekUsedSN', '\App\Http\Controllers\Api\QuotaController@cekUsedSN');
Route::post('/cekUnusedSN', '\App\Http\Controllers\Api\QuotaController@cekUnusedSN');
Route::post('/baseQuota', '\App\Http\Controllers\Api\QuotaController@baseQuota');
Route::post('/cekCompanyId', '\App\Http\Controllers\Api\QuotaController@cekCompanyId');
Route::post('/updateInvalidSerialNumber', '\App\Http\Controllers\Api\QuotaController@updateInvalidSerialNumber');



