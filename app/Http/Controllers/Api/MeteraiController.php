<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CekCredential;
use App\Services\Utils;
use App\Services\UtilsService;
use App\Services\MeteraiService;
use App\Services\CompanyService;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\MapCompany;
use App\Models\Meterai;
use App\Models\MeteraiView;
use App\Models\ListSigner; 
use App\Models\Sign;
use App\Models\Quota;
use App\Models\Base64DokModel;
use App\Models\PricingModel;
use App\Models\JenisDokumen;
use Illuminate\Support\Facades\Storage;
use mikehaertl\pdftk\Pdf;

class MeteraiController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils, MeteraiService $meterai, CompanyService $companyService, UtilsService $utilsService){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
        $this->meterai = $meterai;
        $this->companyService = $companyService;
        $this->utilsService = $utilsService;
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

                //check quota local
                if($this->companyService->cek($quotaMeterai, $cekEmail->id)){
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'You\'ve ran out of quota']);
                }

                $user = User::where('email', $email)->first();
                if($user){
                    $request->validate([
                        'content' => 'required|array',
                        'content.filename' => 'required|max:255|regex:/^[a-zA-Z0-9_.-]+$/u',
                        'content.docpass' => 'nullable|regex:/^[a-zA-Z0-9_.!@#$%&*()^\/?<>,{}+= -]+$/u',
                        'content.base64Doc' => 'required',
                        'content.docType' => 'required|max:255',
                        'content.signer' => 'array|min:1',
                        'content.signer.*.lowerLeftX' => 'required|numeric|max:1000',
                        'content.signer.*.lowerLeftY' => 'required|numeric|max:1000',
                        'content.signer.*.upperRightX' => 'required|numeric|max:1000',
                        'content.signer.*.upperRightY' => 'required|numeric|max:1000',
                        'content.signer.*.page' => 'required|numeric|min:0|not_in:0',
                        'content.signer.*.location' => 'string',
                    ]);
                    
                    $image_base64 = base64_decode($request->input('content.base64Doc'), true);
                    if ($image_base64 === false) {
                        DB::rollBack();
                        return response(['code' => 98, 'message' =>'Document corrupt']);
                    } else {
                        $cekLagi = base64_encode($image_base64);
                        if ($request->input('content.base64Doc') != $cekLagi) {
                            DB::rollBack();
                            return response(['code' => 98, 'message' =>'Document corrupt']);
                        }
                    }

                    $fileName = time() . '.pdf';
                    Storage::disk('minio')->put($user->company_id .'/dok/'.$user->id.'/'.$fileName, $image_base64);

                    $file = file(Storage::disk('minio')->url($user->company_id .'/dok/' . $user->id . '/' . $fileName));
                    $endfile = trim($file[count($file) - 1]);
                    $n = "%%EOF";

                    if ($endfile === $n) {

                        $paramsCek = [
                            "pdf"=> 'sharefolder/'.$user->company_id .'/dok/' . $user->id . '/' . $fileName,
                            "password"=> $request->input('content.docpass')       
                        ];
    
                        $cekPassword = $this->utilsService->callAPI('cek', $paramsCek);
                        if(!isset($cekPassword['code'])) {
                            if($cekPassword['code'] == 1){

                                $sign = new Sign();
                                $sign->name = $fileName;
                                $sign->realname = addslashes($request->input('content.filename'));
                                $sign->users_id = $user->id;
                                $sign->step = 1;
                                $sign->tipe = 5;
                                $sign->status_id = '1';
                                $sign->save();
    
                                $i = 0;
                                $docType = DB::table('jenis_dokumen')->find($request->input('content.docType'));
                                if($docType){
                                    $reason = $docType->nama."|".$request->input('content.docpass');
                                } else {
                                    $reason = "Dokumen Lain-lain"."|".$request->input('content.docpass');
                                }
                                
                                $base64 = new Base64DokModel;
                                $base64->dokumen_id = $sign->id;
                                $base64->base64Doc = $request->input('content.base64Doc');
                                $base64->status = 1;
                                $base64->save();
    
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
                                    $signer->reason = $reason;
                                    $signer->save();
    
                                    $i++;
                                }      
                                DB::commit();
                                return response(['code' => 0 ,'dataId' => $sign->id, 'message' =>'Success']);
                            } else {
                                DB::rollBack();
                                return response(['code' => 98, 'message' =>$cekPassword['message']]);
                            }
                        } else {
                            DB::rollBack();
                            return response(['code' => 98, 'message' =>$cekPassword]);
                        }
                        
                    } else {
                        DB::rollBack();
                        return response(['code' => 98, 'message' =>'Please use pdf file']);
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
        } catch(\Throwable $e) {
            return response(['code' => 98, 'message' => $e->getMessage()]);
        }
    }

    public function cekSNgagal(Request $request) {

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

                    $jenis = JenisDokumen::where('nama', $docType->nama)->first();
                    if(!$jenis){
                        $kode = $jenis->kode;
                    } else {
                        $kode = 3;
                    }
                    
                    $params = [
                        "namadoc"=> ''.$kode.'',
                        "tgldoc"=> date("Y-m-d"),
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

    public function cekDokSN(Request $request, $id=null) {
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

                $user = User::where('email', $email)->first();
                if($user){
                    if($id){
                        $dok = Sign::with('meteraiView')->where('status_id', 8)->where('users_id', $user->id)->where('id', $id)->get();
                    } else {
                        $dok = Sign::with('meteraiView')->where('status_id', 8)->where('users_id', $user->id)->get();
                    }

                    if($dok){
                        $list = [];
                        foreach($dok as $data){                
                            array_push($list, array('dokId' => $data->id, 'fileName' => $data->realname, 'data' => $data->meteraiView));                   
                        }
                        DB::commit();
                        return response(['code' => 0, 'data' => $list ,'message' => 'Success']);
                    } else {
                        DB::rollBack();
                        return response(['code' => 96, 'message' => 'Dokumen not found']);
                    }
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }   
    }

    public function cekSN(Request $request, $id) {
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
                $user = User::where('email', $email)->first();
                if($user){
                    $params=[];
                    $list=[];
                    $serialNumber = $this->meterai->callAPI('api/chanel/stamp/ext?filter='.$id, $params, 'info', 'GET');
                    if(isset($serialNumber['statusCode'])){
                        if($serialNumber['statusCode'] == 00){
                            foreach($serialNumber['result']['data'] as $data){                
                                array_push($list, array('status' => $data['status'], 'fileName' => $data['file'], 'tglupdate' => $data['tglupdate']));                   
                            }
                            DB::commit();
                            return response(['code' => 0, 'data' => $list ,'message' => 'Success']);
                        } else {
                            DB::rollBack();
                            return response(['code' => 98, 'message' => $serialNumber['message']]);
                        }
                    } else {
                        DB::rollBack();
                        return response(['code' => 98, 'message' => 'Cannot connect to peruri']);
                    }                    
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }   
    }

    public function insertQuota(Request $request){
        try{
            $image_base64 = base64_decode($request->input('base64'));
            $fileName = $request->input('sn').'.png';
            Storage::disk('minio')->put('23/dok/24/meterai/'.$fileName, $image_base64);    

            $insertMeterai = Meterai::where('serial_number', $request->input('sn'))->first();
            if(!$insertMeterai){
                $insertMeterai = new Meterai();
                $insertMeterai->serial_number = $request->input('sn');
                $insertMeterai->path = '23/dok/24/meterai/'.$fileName;
                $insertMeterai->status = 0;
                $insertMeterai->company_id = 23;
                $insertMeterai->save();
            }

            $insertQuota = Quota::where('company_id', 23)->first();
            $insertQuota->all = $insertQuota->all + 1;
            $insertQuota->quota = $insertQuota->quota + 1;
            $insertQuota->save();
            return response(['code' => 0, 'message' => 'Sukses']);
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }        
    }
}
