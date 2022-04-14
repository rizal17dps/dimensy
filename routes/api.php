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
Route::get('/getDocument/{id?}', '\App\Http\Controllers\Api\SendController@getDocument');
Route::post('/sendDocument', '\App\Http\Controllers\Api\SendController@sendDocument');
Route::post('/signing', '\App\Http\Controllers\Api\SendController@signing');
Route::post('/getOtp', '\App\Http\Controllers\Api\SendController@getOtp');
Route::post('/download', '\App\Http\Controllers\Api\SendController@download');
Route::get('/getFile', '\App\Http\Controllers\Api\SendController@downloadPath');

//BulkUploadDok
Route::post('/sendBulkDocument', '\App\Http\Controllers\Api\SendBulkController@sendDocument');
Route::post('/getOtpBulk', '\App\Http\Controllers\Api\SendBulkController@getOtp');
Route::post('/signingBulk', '\App\Http\Controllers\Api\SendBulkController@signing');

//Serial Sign
Route::post('/sendDocumentSerial', '\App\Http\Controllers\Api\SendSerialController@sendDocument');
Route::post('/setSignatureSerial', '\App\Http\Controllers\Api\SendSerialController@setSignature');
Route::post('/signingSerial', '\App\Http\Controllers\Api\SendSerialController@signingSerial');

//Paralel Sign
Route::post('/sendDocumentParallel', '\App\Http\Controllers\Api\SendParallelController@sendDocument');
Route::post('/setSignatureParallel', '\App\Http\Controllers\Api\SendParallelController@setSignature');
Route::post('/signingParallel', '\App\Http\Controllers\Api\SendSerialController@signingParallel');

//Stamp
Route::post('/sendStamp', '\App\Http\Controllers\Api\StampController@sendStamp');
Route::post('/signingStamp', '\App\Http\Controllers\Api\StampController@signing');

//Register User
Route::post('/register', '\App\Http\Controllers\Api\RegisterController@registerUser');
Route::post('/verification', '\App\Http\Controllers\Api\RegisterController@verification');

//Create Speciment
Route::post('/speciment', '\App\Http\Controllers\Api\SpecimentController@createSpeciment');

//cek Certificate
Route::post('/certificate', '\App\Http\Controllers\Api\SpecimentController@certificate');
Route::post('/renewal', '\App\Http\Controllers\Api\RegisterController@renewal');

//cek quota
Route::get('/quota', '\App\Http\Controllers\Api\QuotaController@cekQuota');
Route::post('/cekSingleQuota', '\App\Http\Controllers\Api\QuotaController@cekSingleQuota');
Route::post('/historyTrans', '\App\Http\Controllers\Api\QuotaController@historyTrans');
Route::post('/transfer', '\App\Http\Controllers\Api\QuotaController@transfer');
Route::get('/monitor', '\App\Http\Controllers\Api\QuotaController@monitor');

//Meterai
Route::get('/DocType', '\App\Http\Controllers\Api\MeteraiController@jenisDok');
Route::post('/meteraiSigning', '\App\Http\Controllers\Api\MeteraiController@signingMeterai');
Route::post('/generateSN', '\App\Http\Controllers\Api\MeteraiController@generateSN');

//Check Keyla
Route::post('/checkKeyla', '\App\Http\Controllers\Api\SpecimentController@checkKeyla');
Route::post('/registerKeyla', '\App\Http\Controllers\Api\SpecimentController@registerKeyla');
Route::post('/verifKeyla', '\App\Http\Controllers\Api\SpecimentController@verifKeyla');
Route::post('/unregisterKeyla', '\App\Http\Controllers\Api\SpecimentController@unregisterKeyla');