<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\CekCredential;
use App\Services\Utils;
use App\Services\SignService;
use App\Services\CompanyService;
use App\Models\User;
use App\Models\MapCompany;
use App\Models\Sign;
use App\Models\ListSigner;
use App\Models\dokSign;

class SendBulkController extends Controller
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
                    'content.payload' => 'array|min:1',
                    'content.payload.*.filename' => 'required',
                    'content.payload.*.base64Doc' => 'required',
                    'content.signer' => 'array|min:1',
                    'content.signer.*.lowerLeftX' => 'required',
                    'content.signer.*.lowerLeftY' => 'required',
                    'content.signer.*.upperRightX' => 'required',
                    'content.signer.*.upperRightY' => 'required',
                    'content.signer.*.page' => 'required|numeric',
                    'content.signer.*.location' => 'string|regex:/^[a-zA-Z]+$/u',
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

                        $i = 0;
                        $payload = [];
                        $signId = [];
                        $signerIdentity = [];
                        foreach($request->input('content.payload') as $doc){
                            $size = $this->utils->getBase64FileSize($doc['base64Doc']);

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
                                $image_base64 = base64_decode($doc['base64Doc']);
                                $fileName = time() . '.pdf';
                                Storage::disk('minio')->put($user->company_id .'/dok/'.$user->id.'/'.$fileName, $image_base64);

                                $sign = new Sign();
                                $sign->name = $fileName;
                                $sign->realname = $doc['filename'];
                                $sign->users_id = $user->id;
                                $sign->step = 1;
                                $sign->tipe = 1;
                                $sign->status_id = '1';
                                $sign->save();

                                $signer = new ListSigner();
                                $signer->users_id = $user->id;
                                $signer->dokumen_id = $sign->id;
                                $signer->step = 1;
                                $signer->lower_left_x = $request->input('content.signer')[$i]['lowerLeftX'];
                                $signer->lower_left_y = $request->input('content.signer')[$i]['lowerLeftY'];
                                $signer->upper_right_x = $request->input('content.signer')[$i]['upperRightX'];
                                $signer->upper_right_y = $request->input('content.signer')[$i]['upperRightY'];
                                $signer->page = $request->input('content.signer')[$i]['page'];
                                $signer->reason = 'Signed';
                                $signer->location = $request->input('content.signer')[$i]['location'];
                                $signer->save();


                            }
                            array_push($payload, array('fileName' => $doc['filename'], 'base64Document' => $doc['base64Doc']));
                            array_push($signId, array('dataId' => $sign->id));
                            array_push($signerIdentity, array('lowerLeftX' => $signer->lower_left_x, 'lowerLeftY' => $signer->lower_left_y, 'upperRightX' => $signer->upper_right_x, 'upperRightY' => $signer->upper_right_y, 'page' => $signer->page, 'location' => $signer->location, 'reason' => 'Signed'));
                            $i++;
                        }

                        $params = [
                            "param" => [
                                "systemId" => 'PT-DPS',
                                "email" => $email,
                                "payload"=> [
                                    "file" => $payload,
                                    "signer"=>$signerIdentity
                                ]
                            ]
                        ];

                        $signing = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/sendDocumentBulk/v1', $params);
                        
                        if($signing['resultCode'] == 0){
                            $x = 0;
                            foreach($signing['data'] as $data){
                                $dokSign = dokSign::firstWhere('dokumen_id', $signId[$x]['dataId']);
                                if(!$dokSign){
                                    $dokSign = new dokSign();
                                }                    
                                $dokSign->dokumen_id = $signId[$x]['dataId'];
                                $dokSign->orderId = $data['orderId'];
                                $dokSign->save();
                                $x++;
                            }

                            DB::commit();
                            return response(['code' => 0, 'dataId' => $signId, 'message' => 'Success']);
                        } else {
                            DB::rollBack();
                            return response(['code' => 96, 'message' => $signing['resultDesc']]);
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

    public function getOtp(Request $request){
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
                $request->validate([
                    'data' => 'array|min:1',
                    'data.*.dataId' => 'required'
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


                $doks = dokSign::whereIn('dokumen_id', $request->input('data'))->get();
                
                $orderId = [];
                foreach($doks as $data){
                    array_push($orderId, array('orderId' => ''.$data->orderId.''));
                }

                $params = [
                    "requestGetOtpBulk" => [
                        "data" => $orderId,
                        "systemId" => "PT-DPS"
                    ]
                ];
                
                $getOtp = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/getOtpBulk/v1', $params);
                if($getOtp["resultCode"] == "0"){
                    $datas['token'] = $getOtp["data"]["token"];
                    $datas['dataIdBulk'] = $getOtp["data"]["orderIdBulk"];
                    DB::commit();
                    return response()->json(['code'=>0, 'data'=>$datas]);
                } else {
                    DB::rollBack();
                    return response()->json(['code'=>97, 'message'=>$getOtp["resultDesc"]]);
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
                    'dataIdBulk' => 'required',
                    'otpCodeBulk' => 'string',
                    'tokenCodeBulk' => 'string',
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
                                "systemId" => 'PT-DPS',
                                "orderIdBulk" => ''.$request->input('dataIdBulk').'',
                                "tokenBulk" => ''.$request->input('tokenCodeBulk').'',
                                "otpCodeBulk" => ''.$request->input('otpCodeBulk').''
                            ]
                    ];
    
                    $sign = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/signingBulk/v1', $params);
                    if($sign["resultCode"] == 0){
                        $i = 1;
                        foreach($sign["data"] as $signData){
                            $params = [
                                "param" => 
                                [
                                    "systemId" => 'PT-DPS',
                                    "orderId" => ''.$dokSign->orderId.'',
                                ]
                            ];
                            
                            for($i = 1; $i<=3; $i++){
                                $viewDoc = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/downloadDocument/v1', $params);
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

                                    if(!$this->companyService->history($quotaSign, $cekEmail->id)){
                                        DB::rollBack();
                                        return response(['code' => 98, 'message' => 'Error create History']);
                                    }
        
                                    break;                      
                                } else {
                                    $sukses = false;
                                }
                            }
                        }                        

                        if($sukses){
                            
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
}
