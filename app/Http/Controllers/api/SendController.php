<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sign;
use App\Models\User;
use App\Models\ListSigner;
use App\Services\CekCredential;
use App\Services\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SendController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
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
            $header = $request->header('api-key');
            $email = $request->header('email');

            if(!$header){
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email']);
            }

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
                    'content.signer.certificateLevel' => 'required',
                    'content.signer.location' => 'string|regex:/^[a-zA-Z]+$/u',
                    'content.signer.reason' => 'string|max:255',
            ]);

            $cekToken = $this->cekCredential->cekToken($header);
            if(!$cekToken){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Token Mismatch']);
            } else {
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
                                $signer->step = 1;
                                $signer->lower_left_x = $request->input('content.signer.lower_left_x');
                                $signer->lower_left_y = $request->input('content.signer.lower_left_y');
                                $signer->upper_right_x = $request->input('content.signer.upper_right_x');
                                $signer->upper_right_y = $request->input('content.signer.upper_right_y');
                                $signer->page = $request->input('content.signer.page');
                                $signer->dokumen_id = $sign->id;
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
}
