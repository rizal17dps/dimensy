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

class SendSerialController extends Controller
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
                DB::rollBack();
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
                            $sign->tipe = 2;
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
                                    "systemId" => env('SYSTEMID'),
                                    //"uploader"=> auth()->user()->email,
                                    "uploader"=> $cekEmail->email,
                                    "payload"=> [
                                        "filename" => ''.$sign->realname.'',
                                        "base64Document" => ''.base64_encode(Storage::disk('minio')->get($sign->user->company_id .'/dok/' . $sign->users_id . '/' . $sign->name)).'',
                                        "signer" => $email
                                    ]
                                ]
                            ];

                            $sendDoc = $digiSign->callAPI('digitalSignatureFullJwtSandbox/1.0/sendDocumentTier/v1', $params);

                            if($sendDoc["resultCode"] == 0){
                                $dokSign = dokSign::where('dokumen_id', $request->input('idDok'))->first();
                                if(!$dokSign){
                                    $dokSign = new dokSign();
                                }
                                $dokSign->dokumen_id = $sign->id;
                                $dokSign->orderId = $sendDoc["data"]["orderId"];
                                $dokSign->save();

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
}
