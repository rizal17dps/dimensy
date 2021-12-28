<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CekCredential;
use App\Services\Utils;
use Illuminate\Support\Facades\DB;
use App\Mail\SignMail;
use Illuminate\Support\Facades\Mail;
use App\Services\CompanyService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\MapCompany;
use App\Services\SignService;

class RegisterController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils, SignService $sign, CompanyService $companyService){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
        $this->companyService = $companyService;
        $this->sign = $sign;
    }

    public function registerUser(Request $request) {
        DB::beginTransaction();
        try{
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

                $map = MapCompany::with('paket', 'paket.maps')->where('company_id', $cekEmail->company_id)->first();
                $quotaUser = "";
                $quotaOtp = "";
                $quotaKeyla = "";

                foreach($map->paket->maps as $map){
                    if($map->detail->type == 'user'){
                        $quotaUser = $map->detail->id;
                    }
                }

                if($this->companyService->cek($quotaUser, $cekEmail->id)){
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'You\'ve ran out of quota']);
                }

                $englishNames = array(
                    'name' => 'Name',
                    'nik' => 'NIK',
                    'npwp' => 'NPWP',
                    'pob' => 'Place of Birth',
                    'dob' =>'Date of Birth',
                    'city' =>'City',
                    'prov' =>'Province',
                    'foto_ktp' =>'ID Card Picture',
                    'foto_npwp' =>'NPWP Picture',
                    'address' =>'Address',
                    'phone' =>'Handphone Number',
                    'email' =>'Email',
                );

                $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'nik' => ['required', 'digits:16', 'unique:users'],
                    'npwp' => ['max:15'],
                    'pob' => ['required', 'string'],
                    'dob' => ['required', 'string'],
                    'phone' => ['required', 'unique:users,hp'],
                    'address' => ['required', 'string', 'max:255'],
                    'city' => ['required', 'string', 'max:255'],
                    'prov' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'string', 'email', 'max:255', 'unique:users', 'regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix'],
                    'password' => ['required', 'string', 'min:8'],
                    'fotoKtp' => ['required', 'string'],
                ], $englishNames);

                $size = $this->utils->getBase64FileSize($request->input('fotoKtp'));
                $sizeNpwp = $this->utils->getBase64FileSize($request->input('fotoNpwp'));

                if(str_replace(".", "", round($size, 3)) > 2048){
                    return response(['code' => 95, 'message' => 'Upload Limit']);
                }

                if(str_replace(".", "", round($sizeNpwp, 3)) > 2048){
                    return response(['code' => 95, 'message' => 'Upload Limit']);
                }
                
                $user = new User();
                $user->name = $request->input('name');
                $user->email = strtolower($request->input('email'));
                $user->password = Hash::make($request->input('password'));
                $user->nik = $request->input('nik');
                $user->tempat_lahir = $request->input('pob');
                $user->tanggal_lahir = $request->input('dob');
                $user->hp = $request->input('phone');
                $user->alamat = $request->input('address');
                $user->kota = $request->input('city');
                $user->provinsi = $request->input('prov'); 
                $user->company_id = $cekEmail->company_id;
                $user->foto_ktp = $request->input('fotoKtp'); 
                $user->foto_npwp = $request->input('fotoNpwp');
                $user->menuroles = 'user';
                $user->assignRole('user');
                $user->save();

                if(!$this->companyService->history($quotaUser, $cekEmail->id)){
                    DB::rollBack();
                    throw new \Exception('Error Create History Pemakaian', 500);
                }

                $date=date_create($user->tanggal_lahir);
                $params = [
                    "param" => [
                            "name" => $user->name,
                            "phone"=> $user->hp,
                            "email" => $user->email,
                            "password"=> $request->input('password'),
                            "type"=>"INDIVIDUAL",
                            "ktp"=> $user->nik,
                            "ktpPhoto"=> $user->foto_ktp,
                            "npwp"=>$request->input('npwp'),
                            "npwpPhoto"=>$user->foto_ktp,
                            "selfPhoto"=>"",
                            "address"=> $user->alamat,
                            "city"=> $user->kota,
                            "province"=> $user->provinsi,
                            "gender"=>"M",
                            "placeOfBirth"=> $user->tempat_lahir,
                            "dateOfBirth"=> date_format($date,"d/m/Y"),
                            "systemId"=>'PT-DPS'
                    ]
                ];   
                
                $regis = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/registration/v1', $params);
                if($regis["resultCode"] == "0"){
                    DB::commit();
                    return response(['code' => 0, 'message' => 'Registration Successful! Check your email for the activation email']);
                } else {
                    DB::rollBack();
                    return response(['code' => 96, 'message' => $regis['resultDesc']]);
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

    public function verification(Request $request) {
        DB::beginTransaction();
        try{
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
                    'base64video' => 'Video Stream'
                );

                $request->validate([
                    'base64video' => ['required']
                ], $englishNames);

                $size = $this->utils->getBase64FileSize($request->input('base64video'));

                if(str_replace(".", "", round($size, 3)) > 2048){
                    return response(['code' => 95, 'message' => 'Upload Limit']);
                }

                $params = [
                    "param" => [
                            "email" => $cekEmail->email,
                            "videoStream"=> $request->input('base64video'),
                            "systemId"=>'PT-DPS'
                    ]
                ];  
                
                $regisEkyc = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/videoVerification/v1', $params);

                if($regisEkyc["resultCode"] == 0){
                    $user = User::find($cekEmail->id);
                    $user->email_verified_at = date('Y-m-d h:i:s');
                    $user->isexpired = '0';
                    $user->save();

                    DB::commit();
                    return response(['code' => 0, 'message' => 'E-KYC Success']);
                } else {
                    DB::rollBack();
                    return response(['code' => 96, 'message' => $regisEkyc['resultDesc']]);
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

    public function renewal(Request $request) {
        DB::beginTransaction();
        try{
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
                    'base64video' => 'Video Stream'
                );

                $request->validate([
                    'base64video' => ['required']
                ], $englishNames);

                $size = $this->utils->getBase64FileSize($request->input('base64video'));

                if(str_replace(".", "", round($size, 3)) > 2048){
                    return response(['code' => 95, 'message' => 'Upload Limit']);
                }

                $params = [
                    "param" => [
                            "email" => $cekEmail->email,
                            "payload" => [
                                "videoStream"=> $request->input('base64video'),
                            ],                            
                            "systemId"=>'PT-DPS'
                    ]
                ];  
                
                $regisEkyc = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/videoVerificationForRenewal/v1', $params);

                if($regisEkyc["resultCode"] == 0){
                    $user = User::find($cekEmail->id);
                    $user->isexpired = '0';
                    $user->save();

                    DB::commit();
                    return response(['code' => 0, 'message' => 'Renewal Success']);
                } else {
                    DB::rollBack();
                    return response(['code' => 96, 'message' => $regisEkyc['resultDesc']]);
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
