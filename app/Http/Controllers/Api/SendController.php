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
use App\Models\Base64DokModel;
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
                        $dok = Sign::with('meteraiView', 'descView', 'approver')->where('users_id',$user->id)->where('id', (int)$id)->get();
                    } else {
                        $dok = Sign::with('meteraiView', 'descView', 'approver')->where('users_id',$user->id)->get();
                    }

                    if($dok){
                        $list = [];
                        $dataDesc = [];
                        $i = 0;
                        foreach($dok as $data){
                            $resultCode = 99;

                            if($data->stat->id == 1){
                                $resultCode = 95;
                            } else if($data->stat->id == 8) {
                                $resultCode = 0;
                            } else if($data->stat->id == 9) {
                                $resultCode = 97;
                            }

                            foreach($data->approver as $dataApp){
                                if($dataApp->isSign){
                                    $dataCode = 0;
                                    $desc = 'success';
                                } else {
                                    $dataCode = 99;
                                    $desc = 'OnProgress / Error Labelling eMeterai';
                                }

                                array_push($dataDesc, array('code' => $dataCode, 'page' => $dataApp->page, 'desc' => $desc));

                            }

                            array_push($list, array('resultCode' => $resultCode, 'dataId' => $data->id, 'fileName' => $data->realname, 'dataSN' => $data->meteraiView ? '' : $data->meteraiView[0]->serial_number, 'status' => $data->stat->name, 'desc' => $data->descView, 'statusMeteraiDokumen' => $dataDesc));
                            
                            $i++;
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
                    // if($dok->status_id == 8) {
                    //     $data['base64Document'] = base64_encode(Storage::disk('minio')->get($dok->user->company_id .'/dok/' . $dok->users_id . '/' . $dok->name));
                    //     return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);
                    // } else {
                    //     //$data['base64Document'] = base64_encode(Storage::disk('minio')->get($dok->user->company_id .'/dok/' . $dok->users_id . '/' . $dok->name));
                    //     return response(['code' => 10, 'message' => 'Dokumen masih dalam proses antrian']);
                    // }
                    
                    $data['base64Document'] = base64_encode(Storage::disk('minio')->get($dok->user->company_id .'/dok/' . $dok->users_id . '/' . $dok->name));
                    return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);

                } else {
                    return response(['code' => 97, 'message' => 'Document not found']);
                }                
            }
            
        } catch(\Exception $e) {
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function downloadPath(Request $request, $id) {
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
                
                DB::commit();
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            } else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {

                $dok = Sign::find($id);
                
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
