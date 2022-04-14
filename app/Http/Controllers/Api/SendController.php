<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sign;
use App\Models\User;
use App\Models\ListSigner;
use App\Models\dokSign;
use App\Models\MapCompany;
use App\Services\CekCredential;
use App\Services\Utils;
use App\Services\SignService;
use App\Services\CompanyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SendController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils, SignService $sign, CompanyService $companyService){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
        $this->sign = $sign;
        $this->companyService = $companyService;
    }

    public function getDocument(Request $request, $id=null) {
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
                        $dok = Sign::where('users_id',$user->id)->where('id', $id)->get();

                    } else {
                        $dok = Sign::where('users_id',$user->id)->get();

                    }

                    if($dok){
                        $list = [];
                        foreach($dok as $data){
                            array_push($list, array('dataId' => $data->id, 'fileName' => $data->realname, 'status' => $data->stat->name));
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

    public function sendDocument(Request $request) {
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
                    'content' => 'required|array',
                    'content.filename' => 'required',
                    'content.base64Doc' => 'required',
                    'content.signer' => 'array|min:1',
                    'content.signer.lowerLeftX' => 'required',
                    'content.signer.lowerLeftY' => 'required',
                    'content.signer.upperRightX' => 'required',
                    'content.signer.upperRightY' => 'required',
                    'content.signer.page' => 'required|numeric',
                    'content.signer.reason' => 'required|string',
                    'content.signer.location' => 'required|string|regex:/^[a-zA-Z]+$/u',
                ]);            

                $user = User::where('email', $email)->where('is_active', 'true')->first();
                if($user){
                    if($user->company_id == $cekToken){
                        
                        if($this->utils->cekExpired($user->company->mapsCompany->expired_date)){
                            return response(['code' => 95, 'message' => 'Your package has run out, please update your package']);
                        }

                        $map = MapCompany::with('paket', 'paket.maps')->where('company_id', $user->company_id)->first();
                        $quotaSign = "";
                        $quotaOtp = "";
                        $quotaKeyla = "";

                        foreach($map->paket->maps as $map){
                            if($map->detail->type == 'sign'){
                                $quotaSign = $map->detail->id;
                            }
                        }

                        if($this->companyService->cek($quotaSign, $cekEmail->id)){
                            DB::rollBack();
                            return response(['code' => 98, 'message' => 'You\'ve ran out of quota']);
                        }

                        $size = $this->utils->getBase64FileSize($request->input('content.base64Doc'));

                        $limitUpload = DB::table('map_company')
                                        ->join('map_paket', 'map_company.paket_id', '=', 'map_paket.paket_id')
                                        ->join('paket_detail', 'map_paket.paket_detial_id', '=', 'paket_detail.id')
                                        ->where('map_company.company_id', $user->company_id)
                                        ->where('paket_detail.type', 'upload')
                                        ->select('paket_detail.value')->first();
                        
                        if($limitUpload) {
                            $limit = $limitUpload->value."048";
                        } else {
                            $limit = 2048;
                        } 

                        if(str_replace(".", "", round($size, 3)) > $limit){
                            return response(['code' => 95, 'message' => 'Upload Limit']);
                        } else {
                            $image_base64 = base64_decode($request->input('content.base64Doc'));
                            $fileName = time() . '.pdf';
                            Storage::disk('minio')->put($user->company_id .'/dok/'.$user->id.'/'.$fileName, $image_base64);

                            $sign = new Sign();
                            $sign->name = $fileName;
                            $sign->realname = $request->input('content.filename');
                            $sign->users_id = $user->id;
                            $sign->step = 1;
                            $sign->tipe = 1;
                            $sign->status_id = '1';
                            if($sign->save()){
                                $signer = new ListSigner();
                                $signer->users_id = $user->id;
                                $signer->dokumen_id = $sign->id;
                                $signer->step = 1;
                                $signer->lower_left_x = $request->input('content.signer.lowerLeftX');
                                $signer->lower_left_y = $request->input('content.signer.lowerLeftY');
                                $signer->upper_right_x = $request->input('content.signer.upperRightX');
                                $signer->upper_right_y = $request->input('content.signer.upperRightY');
                                $signer->page = $request->input('content.signer.page');
                                $signer->reason = $request->input('content.signer.reason');
                                $signer->location = $request->input('content.signer.location');
                                if($signer->save()){
                                    $doks = Sign::find((int)$sign->id);
                                    $listSigner = ListSigner::where('dokumen_id', $sign->id)->where('users_id', $cekEmail->id)->where('step', $doks->step)->first();
                                    if($listSigner){
                                        $params = [
                                            "param" => [
                                                "systemId" => env('SYSTEMID'),
                                                "email" => $email,
                                                "payload"=> [
                                                    "filename" => ''.$doks->realname.'',
                                                    "base64Document" => ''.base64_encode(Storage::disk('minio')->get($doks->user->company_id .'/dok/' . $doks->users_id . '/' . $doks->name)).'',
                                                    "signer"=>[
                                                        [
                                                            "isVisualSign"=> "YES",
                                                            "lowerLeftX"=> ''.$listSigner->lower_left_x.'',
                                                            "lowerLeftY"=> ''.$listSigner->lower_left_y.'',
                                                            "upperRightX"=> ''.$listSigner->upper_right_x.'',
                                                            "upperRightY"=> ''.$listSigner->upper_right_y.'',
                                                            "page"=> ''.$listSigner->page.'',
                                                            "certificateLevel"=> "NOT_CERTIFIED",
                                                            "varLocation"=> ''.$listSigner->location.'',
                                                            "varReason"=> ''.$listSigner->reason.''
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ];

                                        $signing = $this->sign->callAPI(env('ALL').'/1.0/sendDocument/v1', $params);
                                        if($signing['resultCode'] == 0){
                                            $dokSign = dokSign::firstWhere('dokumen_id', $sign->id);
                                            if(!$dokSign){
                                                $dokSign = new dokSign();
                                            }                    
                                            $dokSign->dokumen_id = $sign->id;
                                            $dokSign->orderId = $signing['data']['orderId'];
                                            $dokSign->users_id = $user->id;
                                            $dokSign->save();

                                            DB::commit();
                                            return response(['code' => 0, 'dataId' => $sign->id, 'message' => 'Success']);
                                        } else {
                                            DB::rollBack();
                                            return response(['code' => 96, 'message' => $signing['resultDesc']]);
                                        }
                                        
                                    } else {
                                        DB::rollBack();
                                        return response(['code' => 97, 'message' => 'Signer not found']);
                                    }
                                }
                            }
                        }                        
                        
                    } else {
                        DB::rollBack();
                        return response(['code' => 97, 'message' => 'User Not Found']);
                    }
                } else {
                    DB::rollBack();
                    return response(['code' => 96, 'message' => 'Email not register']);
                }
            }
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }        
    }

    public function signing(Request $request) {
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
            } else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                if($this->utils->cekExpired($cekEmail->company->mapsCompany->expired_date)){
                    return response()->json(['success'=>false, 'msg'=>'Your package has run out, please update your package']);
                }

                $map = MapCompany::with('paket', 'paket.maps')->where('company_id', $cekEmail->company_id)->first();
                $quotaSign = "";
                $quotaOtp = "";
                $quotaKeyla = "";

                foreach($map->paket->maps as $map){
                    if($map->detail->type == 'sign'){
                        $quotaSign = $map->detail->id;
                    } else if($map->detail->type == 'otp') {
                        $quotaOtp = $map->detail->id;
                    } else if($map->detail->type == 'keyla') {
                        $quotaKeyla = $map->detail->id;
                    }
                }

                if($this->companyService->cek($quotaSign, $cekEmail->id)){
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'You\'ve ran out of quota']);
                }

                $request->validate([
                    'dataId' => 'required',
                    'otpCode' => 'string',
                    'tokenCode' => 'string',
                ]);

                if($this->companyService->cek($quotaSign, $cekEmail->id)){
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'You\'ve ran out of quota']);
                }
                
                if($this->companyService->cek($quotaOtp, $cekEmail->id)){
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'You\'ve ran out of quota']);
                }

                $dokSign = dokSign::where('dokumen_id', $request->input('dataId'))->first();
                if($dokSign){
                    $doks = Sign::find($dokSign->dokumen_id);
                    $params = [
                        "requestSigning" => 
                            [
                                "systemId" => env('SYSTEMID'),
                                "orderId" => ''.$dokSign->orderId.'',
                                "token" => ''.$request->input('tokenCode').'',
                                "otpCode" => ''.$request->input('otpCode').''
                            ]
                    ];
    
                    $sign = $this->sign->callAPI(env('ALL').'/1.0/signing/v1', $params);
                    if($sign["resultCode"] == 0){   
                        $params = [
                            "param" => 
                            [
                                "systemId" => env('SYSTEMID'),
                                "orderId" => ''.$dokSign->orderId.'',
                            ]
                        ];
                        
                        for($i = 1; $i<=3; $i++){
                            $viewDoc = $this->sign->callAPI(env('ALL').'/1.0/downloadDocument/v1', $params);
                            if($viewDoc["resultCode"] == "0"){
                                $image_base64 = base64_decode($viewDoc["data"]["base64Document"]);
                                $fileName = 'SIGNED_'.time().'_'.$doks->realname;
                                Storage::disk('minio')->put($doks->user->company_id .'/dok/'.$doks->users_id.'/ttd/'.$fileName, $image_base64);    
                                
                                $dokSign->name = $fileName;
                                $dokSign->status = 'Signed';
                                $dokSign->save();
            
                                $doks->status_id = 3;                  
                                    
                                $doks->save();
            
                                $cekSign = ListSigner::where('dokumen_id', $doks->id)->where('users_id', $doks->user->id)->whereNull('isSign')->first();
                                
                                if($cekSign){
                                    $cekSign->isSign = '1';
                                    $cekSign->save();
                                }                    
                
                                $sukses = true;
                                break;                      
                            } else {
                                $sukses = false;
                            }
                        }

                        if($sukses){
                            if(!$this->companyService->history($quotaSign, $cekEmail->id)){
                                DB::rollBack();
                                return response(['code' => 98, 'message' => 'Error create History']);
                            }

                            if(!$this->companyService->history($quotaOtp, $cekEmail->id)){
                                DB::rollBack();
                                return response(['code' => 98, 'message' => 'Error create History']);
                            }

                            DB::commit();
                            return response(['code' => 0, 'message' => 'Success', 'dataId'=>$doks->id]);
                        } else {                           
                            return response(['code' => 96, 'message' => $viewDoc["resultDesc"]]);
                            //return back()->withError('Failed to generate '.$viewDoc['resultDesc']);
                        }                       
                        
                    } else {
                        DB::rollBack();
                        return response(['code' => 96, 'message' => $sign["resultDesc"]]);
                    }
                } else {
                    DB::rollBack();
                    return response(['code' => 97, 'message' => 'Document not found']);
                }               
                
            }
            
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function getOtp(Request $request) {
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
            } else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                $request->validate([
                    'dataId' => 'required'
                ]);

                $map = MapCompany::with('paket', 'paket.maps')->where('company_id', $cekEmail->company_id)->first();
                $quotaSign = "";
                $quotaOtp = "";
                $quotaKeyla = "";

                foreach($map->paket->maps as $map){
                    if($map->detail->type == 'sign'){
                        $quotaSign = $map->detail->id;
                    } else if($map->detail->type == 'otp') {
                        $quotaOtp = $map->detail->id;
                    } else if($map->detail->type == 'keyla') {
                        $quotaKeyla = $map->detail->id;
                    }
                }

                if($this->companyService->cek($quotaSign, $cekEmail->id)){
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'You\'ve ran out of quota']);
                }

                if($this->companyService->cek($quotaOtp, $cekEmail->id)){
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'You\'ve ran out of quota']);
                }

                $doks = dokSign::join('users', 'users.id', '=', 'dok_sign.users_id')->where('dokumen_id', (int)$request->input('dataId'))->where('users.email', $email)->first();
                if($doks){
                    $dok = Sign::find($request->input('dataId'));
                    //dd($dok->);
                    $params = [
                        "param" => [
                            "systemId" => env('SYSTEMID'),
                            "orderId" => ''.$doks->orderId.'',
                        ]
                    ];
                    $getOtp = $this->sign->callAPI(env('ALL').'/1.0/getOtp/v1', $params);

                    if($getOtp["resultCode"] == "0"){
                        $data['token'] = $getOtp["data"]["token"];
                        $data['dataId'] = $doks->dokumen_id;
                        DB::commit();
                        return response()->json(['code'=>0, 'data'=>$data]);
                    } else {
                        DB::rollBack();
                        return response()->json(['code'=>97, 'message'=>$getOtp["resultDesc"]]);
                    }
                } else {
                    DB::rollBack();
                    return response()->json(['code'=>97, 'message'=>'dataId not found or not match with the email']);
                }
            }
            
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function download(Request $request) {
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
            } else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {

                $request->validate([
                    'dataId' => 'required'
                ]);

                $dok = Sign::find($request->input('dataId'));
                
                if($dok){
                    if($dok->status_id == 3){
                        $dokSign = dokSign::where('dokumen_id', $dok->id)->where('status', 'Signed')->first();
                        if($dokSign){
                            $data['base64Document'] = base64_encode(Storage::disk('minio')->get($dok->user->company_id .'/dok/' . $dok->users_id . '/ttd/' . $dokSign->name));
                            return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);
                        } else {
                            return response(['code' => 96, 'message' => 'Document not found']);
                        }                  
                    } else if ($dok->status_id == 6) {
                        $dokSign = dokSign::where('dokumen_id', $dok->id)->where('status', 'Stamp')->first();
                        if($dokSign){
                            $data['base64Document'] = base64_encode(Storage::disk('minio')->get($dok->user->company_id .'/dok/' . $dok->users_id . '/stamp/' . $dokSign->name));
                            return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);
                        } else {
                            return response(['code' => 96, 'message' => 'Document not found']);
                        }    
                    }else {
                        $data['base64Document'] = base64_encode(Storage::disk('minio')->get($dok->user->company_id .'/dok/' . $dok->users_id . '/' . $dok->name));
                        return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);
                    }                    
                } else {
                    return response(['code' => 97, 'message' => 'Document not found']);
                }                
            }
            
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function downloadPath(Request $request) {
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
            } else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {

                $request->validate([
                    'dataId' => 'required'
                ]);

                $dok = Sign::find($request->input('dataId'));
                
                if($dok){
                    if($dok->status_id == 3){
                        $dokSign = dokSign::where('dokumen_id', $dok->id)->where('status', 'Signed')->first();
                        if($dokSign){
                            $data['pathDoc'] = Storage::disk('minio')->url($dok->user->company_id .'/dok/' . $dok->users_id . '/ttd/' . $dokSign->name);
                            return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);
                        } else {
                            return response(['code' => 96, 'message' => 'Document not found']);
                        }                  
                    } else if ($dok->status_id == 6) {
                        $dokSign = dokSign::where('dokumen_id', $dok->id)->where('status', 'Stamp')->first();
                        if($dokSign){
                            $data['pathDoc'] = Storage::disk('minio')->url($dok->user->company_id .'/dok/' . $dok->users_id . '/stamp/' . $dokSign->name);
                            return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);
                        } else {
                            return response(['code' => 96, 'message' => 'Document not found']);
                        }    
                    }else {
                        $data['pathDoc'] = Storage::disk('minio')->url($dok->user->company_id .'/dok/' . $dok->users_id . '/' . $dok->name);
                        return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);
                    }                    
                } else {
                    return response(['code' => 97, 'message' => 'Document not found']);
                }                
            }
            
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }
}
