<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CekCredential;
use App\Services\Utils;
use App\Services\SignService;
use App\Services\CompanyService;
use App\Models\User;
use App\Models\MapCompany;
use App\Models\Sign;
use App\Models\ListSigner;
use App\Models\dokSign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SendParallelController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils, SignService $sign, CompanyService $companyService){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
        $this->sign = $sign;
        $this->companyService = $companyService;
    }

    public function sendDocument(Request $request) {
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
                
                $request->validate([
                    'content' => 'required|array',
                    'content.filename' => 'required',
                    'content.base64Doc' => 'required',
                    'content.signer' => 'array|min:1',
                    'content.signer.*.emailSigner' => 'required',
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
                            $sign->tipe = 3;
                            $sign->status_id = '1';
                            $sign->save();
                            
                            $email = [];
                            $i = 1;
                            foreach($request->input('content.signer') as $signer){
                                $cekSigner = User::where('email', $signer['emailSigner'])->first();
                                if(!$cekSigner){
                                    DB::rollBack();
                                    return response(['code' => 97, 'message' => $signer['emailSigner'].' Not Found']);
                                }

                                array_push($email, array('email' => $signer['emailSigner']));

                                $signer = new ListSigner();
                                $signer->step = $i;
                                $signer->users_id = $cekSigner->id;
                                $signer->dokumen_id = $sign->id;
                                $signer->save();
                                $i++;
                            }

                            $params = [
                                "param" => [
                                    "systemId" => 'PT-DPS',
                                    //"uploader"=> auth()->user()->email,
                                    "uploader"=> $cekEmail->email,
                                    "payload"=> [
                                        "filename" => ''.$sign->realname.'',
                                        "base64Document" => ''.base64_encode(Storage::disk('minio')->get($sign->user->company_id .'/dok/' . $sign->users_id . '/' . $sign->name)).'',
                                        "signer" => $email
                                    ]
                                ]
                            ];

                            $sendDoc = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/sendDocumentParallel/v1', $params);

                            if($sendDoc["resultCode"] == 0){
                                $emailLists = ListSigner::where('dokumen_id', $sign->id)->orderBy('id', 'ASC')->get();
                                $i = 0;
                                foreach($sendDoc["data"] as $data){
                                    $dokSign = new dokSign();
                                    $dokSign->dokumen_id = $sign->id;
                                    $dokSign->orderId = $data["orderId"];
                                    $dokSign->parallelId = $sendDoc["orderIdParallel"];
                                    $dokSign->status = 'unsigned';
                                    $dokSign->users_id = $emailLists[$i]->user->id;
                                    $dokSign->save();
                                    $i++;
                                }
                                
                                DB::commit();
                                return response(['code' => 0, 'dataId' => $sign->id, 'message' => 'Success']);
                            } else {
                                DB::rollBack();
                                return response(['code' => 96, 'message' => $sendDoc['resultDesc']]);
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

    public function setSignature(Request $request) {
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
                
                $request->validate([
                    'setSignature' => 'required|array',
                    'setSignature.dataId' => 'required',
                    'setSignature.signer' => 'array|min:1',
                    'setSignature.signer.lowerLeftX' => 'required',
                    'setSignature.signer.lowerLeftY' => 'required',
                    'setSignature.signer.upperRightX' => 'required',
                    'setSignature.signer.upperRightY' => 'required',
                    'setSignature.signer.page' => 'required',
                    'setSignature.signer.location' => 'required'
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

                        $dokSign = dokSign::where('dokumen_id', $request->input('setSignature.dataId'))->where('users_id', $cekEmail->id)->where('status', 'unsigned')->first();

                        $params = [
                            "requestSetSignature" => [
                                "orderId" => ''.$dokSign->orderId.'',
                                "signer"=>
                                [
                                    "isVisualSign"=> "YES",
                                    "lowerLeftX"=> ''.$request->input('setSignature.signer.lowerLeftX').'',
                                    "lowerLeftY"=> ''.$request->input('setSignature.signer.lowerLeftY').'',
                                    "upperRightX"=> ''.$request->input('setSignature.signer.upperRightX').'',
                                    "upperRightY"=> ''.$request->input('setSignature.signer.upperRightY').'',
                                    "page"=> ''.$request->input('setSignature.signer.page').'',
                                    "certificateLevel"=> "NOT_CERTIFIED",
                                    "varLocation"=> ''.$request->input('setSignature.signer.location').'',
                                    "varReason"=> "TTD"
                                ],
                                "systemId" => 'PT-DPS'
                            ]
                        ];
                        
                        $sendSign = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/setSignature/v1', $params);
                        if($sendSign["resultCode"] == 0){
                            
                            DB::commit();
                            return response(['code'=>0, 'message'=>$sendSign["resultDesc"], 'dataId' => $request->input('setSignature.dataId')]);
                        } else {
                            DB::rollBack();
                            return response(['code'=>97, 'message'=>$sendSign["resultDesc"]]);
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

    public function signingParallel(Request $request) {
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

                $dokSign = dokSign::where('dokumen_id', $request->input('setSignature.dataId'))->where('users_id', $cekEmail->id)->where('status', 'unsigned')->first();

                if($dokSign){
                    $doks = Sign::find($dokSign->dokumen_id);
                    $params = [
                        "requestSigning" => 
                            [
                                "systemId" => 'PT-DPS',
                                "orderId" => ''.$dokSign->orderId.'',
                                "token" => ''.$request->input('tokenCode').'',
                                "otpCode" => ''.$request->input('otpCode').''
                            ]
                    ];
    
                    $sign = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/signingParallel/v1', $params);
                    if($sign["resultCode"] == 0 && !isset($sign["data"]["orderIdNextSigner"])){   
                        $find = dokSign::where('orderId', $dokSign->orderId)->first();
                        $find->status = 'Signed';
                        $find->save();
                        
                        $listSign = ListSign::where('dokumen_id', $doks->id)->get();
                        $doneSign = dokSign::where('dokumen_id', $doks->id)->where('status', 'Signed')->get();

                        if($listSign->count() == $doneSign->count()){
                            $params = [
                                "param" => 
                                [
                                    "orderIdParallel" => ''.$find->parallelId.'',
                                    "uploader" => ''.$doks->user->email.'',
                                ]
                            ];

                            for($i = 1; $i<=3; $i++){
                                $viewDoc = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/downloadDocumentParallel/v1', $params);
                                Log::info('Hasil '.json_encode($viewDoc));
                                if($viewDoc["resultCode"] == "0" || !isset($viewDoc["resultCode"])){
                                    $image_base64 = base64_decode($viewDoc["data"]["base64Document"]);
                                    $fileName = 'SIGNED_'.time().'_'.$doks->realname;
                                    Storage::disk('minio')->put($doks->user->company_id .'/dok/'.$doks->users_id.'/ttd/'.$fileName, $image_base64);
                                    
                                    if(!dokSign::where('parallelId', $find->parallelId)->update(['name' => $fileName])){
                                        //DB::rollBack();
                                        return response()->json(['success'=>false, 'msg'=>'Error Download']);
                                    }
                                    
                                    $doks->status_id = 3;
    
                                    $doks->save();
    
                                    $cekSign = ListSigner::where('dokumen_id', $doks->id)->where('users_id', auth()->user()->id)->whereNull('isSign')->first();
                                    
                                    if($cekSign){
                                        $cekSign->isSign = '1';
                                        $cekSign->save();
                                    }
    
                                    DB::commit();
                                    $sukses = true;
                                    break;                                
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
                            $cekSign = ListSigner::where('dokumen_id', $doks->id)->where('users_id', auth()->user()->id)->whereNull('isSign')->first();
                            
                            if($cekSign){
                                $cekSign->isSign = '1';
                                $cekSign->save();
                                DB::commit();
                            }

                            return response(['code' => 0, 'message' => 'Success', 'dataId'=>$doks->id]);
                        }                                           
                        
                    } else {
                        DB::rollBack();
                        return response(['code' => 96, 'message' => $sign["resultDesc"]]);
                    }
                } else if($sign["resultCode"] == "0" && isset($sign["data"]["orderIdNextSigner"])) { 
                    $dokSign->orderId = $viewDoc["data"]["orderIdNextSigner"];
                    $dokSign->save();

                    $dok = Sign::find($dokSign->dokumen_id);
                    $dok->step = $dok->step + 1;
                    $dok->save();

                    DB::commit();
                    return response(['code' => 0, 'message' => 'Success', 'NextDataId'=>$dok->id]);
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
}