<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CekCredential;
use App\Services\Utils;
use App\Services\MeteraiService;
use App\Services\CompanyService;
use App\Services\DimensyService;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\MapCompany;
use App\Models\Meterai;
use App\Models\ListSigner; 
use App\Models\Sign;
use App\Models\Base64DokModel;
use App\Models\PricingModel;
use Illuminate\Support\Facades\Storage;

class MeteraiController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils, MeteraiService $meterai, CompanyService $companyService, DimensyService $dimensyService){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
        $this->meterai = $meterai;
        $this->companyService = $companyService;
        $this->dimensyService = $dimensyService;
    }

    public function jenisDok(Request $request) {
        DB::beginTransaction();
        try{
            if($this->utils->block()){
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }

            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                DB::commit();
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
                $user = User::where('email', $email)->first();
                if($user){
                    $jenisDok = DB::table('jenis_dokumen')->select('id', 'nama')->get();
                    DB::commit();
                    return response(['code' => 0, 'data' => $jenisDok ,'message' =>'Success']);
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }  
    }

    public function signingMeterai(Request $request) {
        DB::beginTransaction();
        try{
            if($this->utils->block()){
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }
            
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                DB::commit();
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

                if($this->utils->cekExpired($cekEmail->company->mapsCompany->expired_date)){
                    return response(['code' => 95, 'message' => 'Your package has run out, please update your package']);
                }

                $map = MapCompany::with('paket', 'paket.maps')->where('company_id', $cekEmail->company_id)->first();
                $quotaMeterai = "";

                foreach($map->paket->maps as $map){
                    if($map->detail->type == 'materai'){
                        $quotaMeterai = $map->detail->id;
                    }
                }

                $paramQuota = [
                    "quota_id"=> "6"
                ];

                $quotaSisa = $this->dimensyService->callAPI('api/cekSingleQuota', $paramQuota);
                
                if($quotaSisa['code'] == 0){
                    if($quotaSisa['data'] <= 0){
                        DB::rollBack();
                        return response(['code' => 98, 'message' => 'You\'ve ran out of quota']);
                    }                    
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Error Check Quota']);
                }

                $user = User::where('email', $email)->first();
                if($user){
                    $request->validate([
                        'content' => 'required|array',
                        'content.filename' => 'required',
                        'content.base64Doc' => 'required',
                        'content.docType' => 'required',
                        'content.signer' => 'array|min:1',
                        'content.signer.*.lowerLeftX' => 'required',
                        'content.signer.*.lowerLeftY' => 'required',
                        'content.signer.*.upperRightX' => 'required',
                        'content.signer.*.upperRightY' => 'required',
                        'content.signer.*.page' => 'required',
                        'content.signer.*.location' => 'string',
                    ]);
                    
                    $image_base64 = base64_decode($request->input('content.base64Doc'));
                    $fileName = time() . '.pdf';
                    Storage::disk('minio')->put($user->company_id .'/dok/'.$user->id.'/'.$fileName, $image_base64);

                    $sign = new Sign();
                    $sign->name = $fileName;
                    $sign->realname = $request->input('content.filename');
                    $sign->users_id = $user->id;
                    $sign->step = 1;
                    $sign->tipe = 5;
                    $sign->status_id = '1';
                    $sign->save();

                    $i = 0;
                    $docType = DB::table('jenis_dokumen')->find($request->input('content.docType'));

                    foreach($request->input('content.signer') as $data){

                        $sign = Sign::find($sign->id);

                        $signer = new ListSigner();
                        $signer->users_id = $user->id;
                        $signer->dokumen_id = $sign->id;
                        $signer->step = 1;
                        $signer->lower_left_x = $data['lowerLeftX'];
                        $signer->lower_left_y = $data['lowerLeftY'];
                        $signer->upper_right_x = $data['upperRightX'];
                        $signer->upper_right_y = $data['upperRightY'];
                        $signer->page = $data['page'];
                        $signer->location = $data['location'];
                        $signer->save();
                                        
                        
                        $cekUnusedMeterai = Meterai::where('status', 0)->whereNull('dokumen_id')->where('company_id', $user->company_id)->first();
                        $fileNameFinal = 'METERAI_'.time().'_'.$sign->realname;

                        if($cekUnusedMeterai){

                            $cek = $this->meterai->getJwt();

                            $paramSigns = [
                                "certificatelevel"=> "NOT_CERTIFIED",
                                "dest"=> '/sharefolder/'.$sign->user->company_id .'/dok/'.$sign->users_id.'/'.$fileNameFinal,
                                "docpass"=> ''.$request->input('content.docpass').'',
                                "jwToken"=> $cek["token"],
                                "location"=> ''.$data['location'].'',
                                "profileName"=> "emeteraicertificateSigner",
                                "reason"=> $docType ? $docType->nama : 'Dokumen Lain-lain',
                                "refToken"=> $cekUnusedMeterai->serial_number,
                                "spesimenPath"=> '/sharefolder/'.$cekUnusedMeterai->path,
                                "src"=> '/sharefolder/'.$sign->user->company_id .'/dok/' . $sign->users_id . '/' . $sign->name,
                                "visLLX"=> $data['lowerLeftX'],
                                "visLLY"=> $data['lowerLeftY'],
                                "visURX"=> $data['upperRightX'],
                                "visURY"=> $data['upperRightY'],
                                "visSignaturePage"=> $data['page'],
                            ];

                            $signMeterai = $this->meterai->callAPI('adapter/pdfsigning/rest/docSigningZ', $paramSigns, 'keyStamp', 'POST');
                            
                            if($signMeterai['errorCode'] == 0){
                                $cekDoks = Sign::find($sign->id);
                                $cekDoks->status_id = 8;
                                $cekDoks->name = $fileNameFinal;
                                $cekDoks->save();
            
                                $cekUnusedMeterai->status = 1;
                                $cekUnusedMeterai->dokumen_id = $sign->id;
                                $cekUnusedMeterai->save();

                                $Basepricing = PricingModel::where('name_id', 6)->where('company_id', $user->company_id)->first();

                                // if(!$this->companyService->history($quotaMeterai, $cekEmail->id)){
                                //     DB::rollBack();
                                //     return response(['code' => 98, 'message' => 'Error create History']);
                                // }

                                $historyTrans = [
                                    "paket"=> "materai"
                                ];
                
                                $historySisa = $this->dimensyService->callAPI('api/historyTrans', $historyTrans);
                                if($historySisa['code'] != 0){
                                    DB::rollBack();
                                    return response(['code' => 98, 'message' => 'Error Create History Pemakaian']);
                                }

                                if(!$this->companyService->historyPemakaian($quotaMeterai, $cekEmail->id, isset($Basepricing->price) ? $Basepricing->price : '10800')){
                                    DB::rollBack();
                                    return response(['code' => 98, 'message' => 'Error Create History Pemakaian']);
                                }

                                if(!$this->companyService->quotaKurang($quotaMeterai, $user->company_id)){
                                    DB::rollBack();
                                    return response(['code' => 98, 'message' => 'Error Create History Pemakaian']);
                                }

                                DB::commit();
                                //
                            } else {
                                DB::rollBack();
                                return response(['code' => 95, 'message' => $signMeterai['errorMessage']]);
                            }  
                        } else {
                            DB::rollBack();
                            return response(['code' => 95, 'message' => "Please check the speciment meterai"]);                                
                        }
                        $i++;
                    }      

                    return response(['code' => 0 ,'dataId' => $sign->id, 'message' =>'Success']);
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }
            }
        } catch(\Illuminate\Validation\ValidationException $e) {
            return response(['code' => 99, 'message' => $e->errors()]);
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }  
    }

    public function generateSN(Request $request) {
        DB::beginTransaction();
        try{
            if($this->utils->block()){
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }
            
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                DB::commit();
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
                if($this->utils->cekExpired($cekEmail->company->mapsCompany->expired_date)){
                    return response(['code' => 95, 'message' => 'Your package has run out, please update your package']);
                }

                $map = MapCompany::with('paket', 'paket.maps')->where('company_id', $cekEmail->company_id)->first();
                $quotaMeterai = "";

                foreach($map->paket->maps as $map){
                    if($map->detail->type == 'materai'){
                        $quotaMeterai = $map->detail->id;
                    }
                }

                if($this->companyService->cek($quotaMeterai, $cekEmail->id)){
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'You\'ve ran out of quota']);
                }

                $request->validate([
                    'content' => 'required|array',
                    'content.namafile' => 'required',
                    'content.docType' => 'required'
                ]);

                $docType = DB::table('jenis_dokumen')->find($request->input('content.docType'));

                $user = User::where('email', $email)->first();
                if($user){
                    
                    $params = [
                        "namadoc"=> $docType ? $docType->nama : 'Dokumen Lain-lain',
                        "nodoc"=> "",
                        "tgldoc"=> date("Y-m-d"),
                        "namejidentitas" => "KTP",
                        "noidentitas" => $user->nik,
                        "namedipungut" => $user->name,
                        "nilaidoc"=> "10000",
                        "namafile"=> $request->input('content.docType'),
                        "snOnly"=> false,
                        "isUpload"=> false                     
                    ];

                    $serialNumber = $this->meterai->callAPI('chanel/stampv2', $params, 'stamp', 'POST');
            
                    if($serialNumber["statusCode"] == "00"){
                        $image_base64 = base64_decode($serialNumber["result"]["image"]);
                        $fileName = $serialNumber["result"]["sn"].'.png';
                        Storage::disk('minio')->put($user->company_id .'/dok/'.$user->id.'/meterai/'.$fileName, $image_base64);
                                
                        $meterai = new Meterai();
                        $meterai->serial_number = $serialNumber["result"]["sn"];
                        $meterai->path = $user->company_id .'/dok/'.$user->id.'/meterai/'.$fileName;
                        $meterai->status = 0;
                        $meterai->company_id = $user->company_id;
                        $meterai->created_at = date("Y-m-d H:i:s");
                        $meterai->save();

                        if(!$this->companyService->history($quotaMeterai, $cekEmail->id)){
                            DB::rollBack();
                            return response(['code' => 98, 'message' => 'Error create History']);
                        }
                        
                        DB::commit();
                        return response(['code' => 0 ,'result' => $serialNumber["result"], 'message' =>'Success']);
                    }
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }                
            }            
        } catch(\Illuminate\Validation\ValidationException $e) {
            return response(['code' => 99, 'message' => $e->errors()]);
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }               
    }
}
