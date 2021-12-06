<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sign;
use App\Models\User;
use App\Models\ListSigner;
use App\Models\dokSign;
use App\Services\CekCredential;
use App\Services\Utils;
use App\Services\SignService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SendController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils, SignService $sign){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
        $this->sign = $sign;
    }

    public function getDocument(Request $request, $id) {
        try{
            $header = $request->header('dimensy-api-key');

            if(!$header){
                return response(['code' => 98, 'message' => 'Required Token']);
            }

            $doc = Sign::find((int)$id);
            if($doc){
                $cekToken = $this->cekCredential->cekToken($header);
                if(!$cekToken){
                    return response(['code' => 98, 'message' => 'Token Mismatch']);
                } else {
                    if($doc->user->company_id == $cekToken){
                        return response(['code' => 0, 'data' => $doc->user->company_id, 'message' => 'Success']);
                    } else {
                        return response(['code' => 97, 'message' => 'Document Not Found']);
                    }                    
                }
                
            } else {
                return response(['code' => 97, 'data' => '', 'message' => 'Document Not Found']);
            }

            
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }        
    }

    public function sendDocument(Request $request) {
        DB::beginTransaction();
        try{
            $header = $request->header('apiKey');
            $email = $request->header('email');

            // if(!$header){
            //     return response(['code' => 98, 'message' => 'Api Key Required']);
            // }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email Required']);
            }

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Token Mismatch']);
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
                    'content.signer.location' => 'string|regex:/^[a-zA-Z]+$/u',
                    'content.signer.reason' => 'string|max:255',
                ]);            

                $user = User::where('email', $email)->where('is_active', 'true')->first();
                if($user){
                    if($user->company_id == $cekToken){                        
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
                                $signer->page = $request->input('content.signer.page');
                                $signer->reason = $request->input('content.signer.reason');
                                $signer->location = $request->input('content.signer.location');
                                if($signer->save()){
                                    DB::commit();
                                    return response(['code' => 0, 'dataId' => $sign->id, 'message' => 'Success']);
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
            $header = $request->header('apiKey');
            $email = $request->header('email');

            // if(!$header){
            //     return response(['code' => 98, 'message' => 'Api Key Required']);
            // }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email Required']);
            }

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Token Mismatch']);
            } else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                $request->validate([
                    'id' => 'required'
                ]);

                $doks = Sign::find((int)$request->input('id'));
                if($doks){
                    $listSigner = ListSigner::where('dokumen_id', (int)$request->input('id'))->where('users_id', $cekEmail)->where('step', $doks->step)->first();
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

                        $signing = $this->sign->callAPI('digitalSignatureFullJwtSandbox/1.0/sendDocument/v1', $params);
                        if($signing['resultCode'] == 0){
                            $params = [
                                "param" => [
                                    "systemId" => env('SYSTEMID'),
                                    "orderId" => ''.$signing['data']['orderId'].'',
                                ]
                            ];

                            $dokSign = dokSign::firstWhere('dokumen_id', $request->input('id'));
                            if(!$dokSign){
                                $dokSign = new dokSign();
                            }                    
                            $dokSign->dokumen_id = $request->input('id');
                            $dokSign->orderId = $signing['data']['orderId'];
                            $dokSign->save();

                            DB::commit();
                            return response(['code' => 0, 'orderId' => $dokSign->orderId, 'message' => 'Success']);
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
            
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }
}
