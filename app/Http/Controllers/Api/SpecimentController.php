<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CekCredential;
use App\Services\Utils;
use App\Services\CompanyService;
use App\Services\SignService;
use Illuminate\Support\Facades\DB;
use App\Models\Speciment;


class SpecimentController extends Controller
{
    //

    public function __construct(CekCredential $cekCredential, Utils $utils, SignService $sign, CompanyService $companyService){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
        $this->companyService = $companyService;
        $this->sign = $sign;
    }

    public function createSpeciment(Request $request) {
        DB::beginTransaction();
        try{
            if($this->utils->block()){
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }
            
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email Required']);
            }            

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                $this->utils->logBruteForce(\Request::getClientIp(), $header, $email);
                DB::commit();
                
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            }  else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {

                $englishNames = array(
                    'base64sign' => 'Signature'
                );

                $request->validate([
                    'base64sign' => ['required']
                ], $englishNames);

                $size = $this->utils->getBase64FileSize($request->input('base64sign'));

                if(str_replace(".", "", round($size, 3)) > 2048){
                    return response(['code' => 95, 'message' => 'Upload Limit']);
                }

                $speciment = Speciment::where('users_id', $cekEmail->id)->first();
                if(!$speciment){
                    $speciment = new Speciment();
                }
                
                $speciment->users_id = $cekEmail->id;
                $speciment->name = 'FromAPI';
                $speciment->file = $request->input('base64sign');
                $speciment->save();

                $params = [
                    "param" => [
                            "systemId"=> 'PT-DPS',
                            "email"=> $cekEmail->email,
                            "speciment"=> $request->input('base64sign')
                    ]
                ];   
                
                $ttd = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/sendSpeciment/v1', $params);
                if($ttd["resultCode"] == "0"){
                    DB::commit();
                    return response(['code' => 0, 'message' => 'Successful']);
                } else {
                    DB::rollBack();
                    return response(['code' => 96, 'message' => $ttd['resultDesc']]);
                }
            }
        } catch(\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response(['code' => 97, 'message' => $e->errors()]);
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function certificate(Request $request) {
        DB::beginTransaction();
        try{
            if($this->utils->block()){
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }
            
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email Required']);
            }            

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                $this->utils->logBruteForce(\Request::getClientIp(), $header, $email);
                DB::commit();
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            }  else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                $params = [
                    "param" => [
                            "systemId"=> 'PT-DPS',
                            "email"=> $cekEmail->email,
                    ]
                ];   
                
                $crt = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/checkCertificate/v1', $params);
                if($crt["resultCode"] == "0"){
                    if($crt["data"]['isExpired'] == 1){
                        $data['isExpired'] = 'Certificate has been expired' ;
                    } else if ($crt["data"]['isExpired'] == 2) {
                        $data['isExpired'] = 'certificate validity period <= 30 days' ;
                    } else if ($crt["data"]['isExpired'] == 0) {
                        $data['isExpired'] = 'certificate validity period >= 30 days' ;
                    }

                    $data['email'] = $cekEmail->email;
                    
                    DB::commit();
                    return response(['code' => 0, 'message' => 'Successful', 'data' => $data]);
                } else {
                    DB::rollBack();
                    return response(['code' => 96, 'message' => $crt['resultDesc']]);
                }
            }
        } catch(\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response(['code' => 97, 'message' => $e->errors()]);
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function checkKeyla(Request $request) {
        DB::beginTransaction();
        try{
            if($this->utils->block()){
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }
            
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email Required']);
            }            

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                $this->utils->logBruteForce(\Request::getClientIp(), $header, $email);
                DB::commit();
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            }  else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                $params = [
                    "param" => [
                            "systemId"=> 'PT-DPS',
                            "email"=> $cekEmail->email,
                    ]
                ];   
                
                $crt = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/keylaCheck/v1', $params);
                if($crt["resultCode"] == "0"){
                    DB::commit();
                    return response(['code' => 0, 'message' => 'Successful']);
                } else {
                    DB::rollBack();
                    return response(['code' => 96, 'message' => $crt['resultDesc']]);
                }
            }
        } catch(\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response(['code' => 97, 'message' => $e->errors()]);
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function registerKeyla(Request $request) {
        DB::beginTransaction();
        try{
            if($this->utils->block()){
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }
            
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email Required']);
            }            

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                $this->utils->logBruteForce(\Request::getClientIp(), $header, $email);
                DB::commit();
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            }  else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                $params = [
                    "param" => [
                            "systemId"=> 'PT-DPS',
                            "email"=> $cekEmail->email,
                    ]
                ];   
                
                $crt = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/keylaRegister/v1', $params);
                if($crt["resultCode"] == "0"){
                    DB::commit();
                    return response(['code' => 0, 'message' => 'Successful', 'qrCode' => $crt["data"]["qrImage"]]);
                } else {
                    DB::rollBack();
                    return response(['code' => 96, 'message' => $crt['resultDesc']]);
                }
            }
        } catch(\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response(['code' => 97, 'message' => $e->errors()]);
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function verifKeyla(Request $request) {
        DB::beginTransaction();
        try{
            if($this->utils->block()){
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }
            
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email Required']);
            }            

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                $this->utils->logBruteForce(\Request::getClientIp(), $header, $email);
                DB::commit();
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            }  else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                $request->validate([
                    'token' => ['required']
                ]);

                $params = [
                    "param" => [
                            "systemId"=> 'PT-DPS',
                            "email"=> $cekEmail->email,
                            "token"=> $request->input('token'),
                    ]
                ];   
                
                $crt = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/keylaVerify/v1', $params);
                if($crt["resultCode"] == "0"){
                    DB::commit();
                    return response(['code' => 0, 'message' => 'Successful']);
                } else {
                    DB::rollBack();
                    return response(['code' => 96, 'message' => $crt['resultDesc']]);
                }
            }
        } catch(\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response(['code' => 97, 'message' => $e->errors()]);
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function unregisterKeyla(Request $request) {
        DB::beginTransaction();
        try{
            if($this->utils->block()){
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }
            
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email Required']);
            }            

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                $this->utils->logBruteForce(\Request::getClientIp(), $header, $email);
                DB::commit();
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            }  else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                $request->validate([
                    'qrImage' => ['required']
                ]);

                $params = [
                    "param" => [
                            "systemId"=> 'PT-DPS',
                            "email"=> $cekEmail->email,
                            "qrImage"=> $request->input('qrImage'),
                    ]
                ];   
                
                $crt = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/keylaDeregister/v1', $params);
                if($crt["resultCode"] == "0"){
                    DB::commit();
                    return response(['code' => 0, 'message' => 'Successful']);
                } else {
                    DB::rollBack();
                    return response(['code' => 96, 'message' => $crt['resultDesc']]);
                }
            }
        } catch(\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response(['code' => 97, 'message' => $e->errors()]);
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }
}
