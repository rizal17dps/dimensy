<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CekCredential;
use App\Services\Utils;

class RegisterController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
    }

    public function registerUser(Request $request) {
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
                DB::rollBack();
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            }  else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                
                $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'nik' => ['required', 'digits:16', 'unique:users'],
                    'npwp' => ['max:15'],
                    'pob' => ['required', 'string'],
                    'dob' => ['required', 'string'],
                    'phone' => ['required', 'numeric', 'unique:users'],
                    'address' => ['required', 'string', 'max:255'],
                    'city' => ['required', 'string', 'max:255'],
                    'prov' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'string', 'email', 'max:255', 'unique:users', 'regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix'],
                    'password' => ['required', 'string', 'min:8'],
                    'foto_ktp' => ['required', 'string'],
                    'foto_npwp' => ['string'],
                ]);

                $size = $this->utils->getBase64FileSize($request->input('foto_ktp'));

                if(str_replace(".", "", round($size, 3)) > 2048){
                    return response(['code' => 95, 'message' => 'Upload Limit']);
                }

                
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }   
    }
}
