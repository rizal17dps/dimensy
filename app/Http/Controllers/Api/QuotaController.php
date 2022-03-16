<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PricingModel;
use App\Services\CompanyService;
use App\Services\CekCredential;
use App\Services\Utils;
use App\Models\MapCompany;
use App\Models\User;
use App\Models\Quota;
use Illuminate\Support\Facades\DB;

class QuotaController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils, CompanyService $companyService){
        $this->cekCredential = $cekCredential;
        $this->companyService = $companyService;
        $this->utils = $utils;
    }

    public function cekQuota(Request $request) {
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

                $user = User::where('email', $email)->first();
                if($user){
                    $listDetail = [];
                    foreach($user->company->quota as $data){
                        if($data->detail->type == 'storage'){
                            $quota = round($data->quota/pow(1024,3), 2);
                        } else {
                            $quota = $data->quota;
                        }
                        array_push($listDetail, array('name' => $data->detail->detailName->name, 'quota' => $quota));
                    }
                    $list= [];
                    $list['companyName'] = $user->company->name;
                    $list['package']['name'] = $user->company->mapsCompany->paket->name;
                    $list['package']['expired'] = $user->company->mapsCompany->expired_date;
                    $list['package']['detail'] = $listDetail;
                    DB::commit();
                    return response(['code' => 0, 'data' => $list ,'message' =>'Success']);
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }  
    }

    public function cekSingleQuota(Request $request) {
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

                $user = User::where('email', $email)->first();
                if($user){
                    $quota = Quota::join('paket_detail', 'quota_company.paket_detail_id', '=', 'paket_detail.id')->where('quota_company.company_id', $user->company_id)->where('paket_detail.detail_name_id', $request->input('quota_id'))->first();

                    DB::commit();
                    return response(['code' => 0, 'data' => $quota->quota ,'message' =>'Success']);
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }  
    }

    public function historyTrans(Request $request) {
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

                $user = User::where('email', $email)->first();
                if($user){
                    $Basepricing = PricingModel::where('name_id', 6)->where('company_id', $user->company_id)->first();
                    $map = MapCompany::with('paket', 'paket.maps')->where('company_id', $cekEmail->company_id)->first();
                    $quotaMeterai = "";

                    foreach($map->paket->maps as $map){
                        //materai
                        if($map->detail->type == $request->input('paket')){
                            $quotaMeterai = $map->detail->id;
                        }
                    }

                    if(!$this->companyService->historyPemakaian($quotaMeterai, $cekEmail->id, isset($Basepricing->price) ? $Basepricing->price : '10200')){
                        DB::rollBack();
                        return response(['code' => 98, 'message' => 'Error Create History Pemakaian']);
                    }

                    if(!$this->companyService->quotaKurang($quotaMeterai, $user->company_id)){
                        DB::rollBack();
                        return response(['code' => 98, 'message' => 'Error Create History Pemakaian']);
                    }

                    DB::commit();
                    return response(['code' => 0,'message' =>'Success']);
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }
}
