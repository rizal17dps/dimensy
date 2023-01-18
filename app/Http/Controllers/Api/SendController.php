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
use Illuminate\Support\Facades\Log;
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

                            array_push($list, array('resultCode' => $resultCode, 'dataId' => $data->id, 'fileName' => $data->realname, 'dataSN' => $data->meteraiView, 'status' => $data->stat->name, 'desc' => $data->descView));
                            
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
        $start = microtime(true);
        DB::beginTransaction();
        try{
            config(['logging.channels.api_log.path' => storage_path('logs/api/dimensy-'.date("Y-m-d H").'.log')]);
            if($this->utils->block()){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id  Response time: ".$time_elapsed_secs);
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }
            
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Api Key Required  Response time: ".$time_elapsed_secs);
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Email Required  Response time: ".$time_elapsed_secs);
                return response(['code' => 98, 'message' => 'Email Required']);
            }

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                $this->utils->logBruteForce(\Request::getClientIp(), $header, $email);
                DB::commit();
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - apiKey Mismatch  Response time: ".$time_elapsed_secs);
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            } else if(!$cekEmail){
                DB::rollBack();
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Email Not Found  Response time: ".$time_elapsed_secs);
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {

                $request->validate([
                    'dataId' => 'required'
                ]);

                $dok = Sign::find($request->input('dataId'));
                
                if($dok){
                    $data['base64Document'] = base64_encode(Storage::disk('minio')->get($dok->user->company_id .'/dok/' . $dok->users_id . '/' . $dok->name));
                    $time_elapsed_secs = microtime(true) - $start;
                    Log::channel('api_log')->info("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Success  Response time: ".$time_elapsed_secs);
                    return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);

                } else {
                    $time_elapsed_secs = microtime(true) - $start;
                    Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Document not found  Response time: ".$time_elapsed_secs);
                    return response(['code' => 97, 'message' => 'Document not found']);
                }                
            }
            
        } catch(\Exception $e) {
            $time_elapsed_secs = microtime(true) - $start;
            Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - ".$e->getMessage()."  Response time: ".$time_elapsed_secs);
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function downloadPath(Request $request, $id) {
        $start = microtime(true);
        DB::beginTransaction();
        try{
            config(['logging.channels.api_log.path' => storage_path('logs/api/dimensy-'.date("Y-m-d H").'.log')]);
            if($this->utils->block()){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id  Response time: ".$time_elapsed_secs);
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }
            
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Api Key Required  Response time: ".$time_elapsed_secs);
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Email Required  Response time: ".$time_elapsed_secs);
                return response(['code' => 98, 'message' => 'Email Required']);
            }

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - apiKey Mismatch  Response time: ".$time_elapsed_secs);
                DB::commit();
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            } else if(!$cekEmail){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Email Not Found  Response time: ".$time_elapsed_secs);
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {

                $dok = Sign::find($id);
                
                if($dok){
                    if($dok->status_id == 3){
                        $dokSign = dokSign::where('dokumen_id', $dok->id)->where('status', 'Signed')->first();
                        if($dokSign){
                            $data['pathDoc'] = Storage::disk('minio')->url($dok->user->company_id .'/dok/' . $dok->users_id . '/ttd/' . $dokSign->name);
                            $time_elapsed_secs = microtime(true) - $start;
                            Log::channel('api_log')->info("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Success  Response time: ".$time_elapsed_secs);
                            return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);
                        } else {
                            $time_elapsed_secs = microtime(true) - $start;
                            Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Document Not Found  Response time: ".$time_elapsed_secs);
                            return response(['code' => 96, 'message' => 'Document not found']);
                        }                  
                    } else if ($dok->status_id == 6) {
                        $dokSign = dokSign::where('dokumen_id', $dok->id)->where('status', 'Stamp')->first();
                        if($dokSign){
                            $data['pathDoc'] = Storage::disk('minio')->url($dok->user->company_id .'/dok/' . $dok->users_id . '/stamp/' . $dokSign->name);
                            $time_elapsed_secs = microtime(true) - $start;
                            Log::channel('api_log')->info("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Success  Response time: ".$time_elapsed_secs);
                            return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);
                        } else {
                            $time_elapsed_secs = microtime(true) - $start;
                            Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Document Not Found  Response time: ".$time_elapsed_secs);
                            return response(['code' => 96, 'message' => 'Document not found']);
                        }    
                    }else {
                        $data['pathDoc'] = Storage::disk('minio')->url($dok->user->company_id .'/dok/' . $dok->users_id . '/' . $dok->name);
                        $time_elapsed_secs = microtime(true) - $start;
                        Log::channel('api_log')->info("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Success  Response time: ".$time_elapsed_secs);
                        return response(['code' => 0, 'message' => 'Success', 'data'=>$data]);
                    }                    
                } else {
                    $time_elapsed_secs = microtime(true) - $start;
                    Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Document Not Found  Response time: ".$time_elapsed_secs);
                    return response(['code' => 97, 'message' => 'Document not found']);
                }                
            }
            
        } catch(\Exception $e) {
            $time_elapsed_secs = microtime(true) - $start;
            Log::channel('api_log')->error("IP : ".\Request::ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - ".$e->getMessage()."  Response time: ".$time_elapsed_secs);
            DB::rollBack();
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }
}
