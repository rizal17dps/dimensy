<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Base64DokModel;
use App\Models\PricingModel;
use App\Models\Meterai;
use App\Services\CompanyService;
use App\Services\CekCredential;
use App\Services\DimensyService;
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

    public function transfer(Request $request, DimensyService $dimensyService){
        DB::beginTransaction();
        try{
            //transfer meterai
            if($request->input('paket') == 6){
                $pindah = new Meterai();
                $pindah->company_id = $request->input('id');
                $pindah->save();
            }
                            
            $pricing = PricingModel::where('name_id', $request->input('paket'))->where('company_id', $request->input('id'))->first();
            if(!$pricing){
                $pricing = new PricingModel();
            }            
            $pricing->company_id = $request->input('id');
            $pricing->name_id = $request->input('paket');
            $pricing->price = preg_replace("/[^0-9]/", "", $request->input('price'));
            $pricing->qty = $request->input('qty');
            $pricing->save();

            $paketId='0';
            $satuan = 'Tahun';
            $drs = "year";
            
            $dt1 = new DateTime();
            $today = $dt1->format("Y-m-d");

            $dt2 = new DateTime("+ 1 year");
            $dateExp = $dt2->format("Y-m-d");   
            $mapCompany = MapCompany::where('company_id', $request->input('id'))->first();
            if($mapCompany){
                $paketId = $mapCompany->paket_id;
            } else {
                $paket = new Paket();
                $paket->name = 'Paket-'.$request->input('id');
                $paket->icon = 'no-icon';
                $paket->durasi = '1';
                $paket->satuan = 'Tahun';
                $paket->company_id = $request->input('id');
                $paket->save();

                $storeMapCompany = new MapCompany();
                $storeMapCompany->paket_id = $paket->id;
                $storeMapCompany->company_id = $request->input('id');
                $storeMapCompany->expired_date = $dateExp;
                $storeMapCompany->save();

                $paketId = $paket->id;
            }

            $paketDetail = PaketDetail::where('detail_name_id', $request->input('paket'))->where('company_id', $request->input('id'))->first();
            if(!$paketDetail){
                $paketDetail = new PaketDetail();
            }            
            $paketDetail->value = $request->input('qty');
            $paketDetail->satuan = $infoDetil->satuan;
            $paketDetail->type = $infoDetil->type;
            $paketDetail->company_id = $request->input('id');
            $paketDetail->detail_name_id = $request->input('paket');
            $paketDetail->save();

            $mapPaket = new MapPaket();
            $mapPaket->paket_id = $paketId;
            $mapPaket->paket_detial_id = $paketDetail->id;
            $mapPaket->save();

            $quota = Quota::where('company_id', $request->input('id'))->where('paket_detail_id', $paketDetail->id)->first();
            if($quota){
                if($infoDetil->type == 'storage'){                
                    $quota->quota = $quota->quota + ($request->input('qty') * pow(1024, 3));
                } else {
                    $quota->quota = $quota->quota + $request->input('qty');
                }
                $quota->all = $quota->all + $request->input('qty');
            } else {
                $quota = new Quota();
                $quota->paket_detail_id = $paketDetail->id;
                $quota->company_id = $request->input('id');
                if($infoDetil->type == 'storage'){                
                    $quota->quota = $request->input('qty') * pow(1024, 3);
                } else {
                    $quota->quota = $request->input('qty');
                }
                $quota->all = $request->input('qty');
            }
            
            $quota->save();       
            
            $cekSisaQuota->quota = $cekSisaQuota->quota - $pengurangan;
            $cekSisaQuota->save();

            // if($request->input('paket') == 6){
            //     $Basepricing = PricingModel::where('name_id', $request->input('paket'))->where('company_id', $cekEmail->company_id)->first();

            //     for($i = 1; $i <= $request->input('qty'); $i++){
            //         if(!$companyService->historyPemakaian($cekMapCompany->id, $cekEmail->id, isset($Basepricing->price) ? $Basepricing->price : '10800')){
            //             DB::rollBack();
            //             throw new \Exception('Error Create History Pemakaian', 500);
            //         }
            //     } 
            // } else {
            //     if(!$companyService->historyPemakaian($cekMapCompany->id, $cekEmail->id, "Transfer quota sebesar ".$request->input('qty')."ke ".$request->input('nama'))){
            //         DB::rollBack();
            //         throw new \Exception('Error Create History Pemakaian', 500);
            //     }
            // } 
            
            $Basepricing = PricingModel::where('name_id', $request->input('paket'))->where('company_id', $cekEmail->company_id)->first();

                for($i = 1; $i <= $request->input('qty'); $i++){
                    if(!$this->companyService->historyPemakaian($cekMapCompany->id, $cekEmail->id, isset($Basepricing->price) ? $Basepricing->price : '10800')){
                        DB::rollBack();
                        return response(['code' => 98, 'message' => 'Error Create History Pemakaian']);
                    }
                } 

            DB::commit();
            return response(['code' => 0,'message' =>'Success']);
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function monitor(){
        DB::beginTransaction();
        try{
            $list = [];

            $countBulan = Base64DokModel::whereRaw("DATE_PART('month', created_at) = DATE_PART('month', CURRENT_DATE)")->whereRaw("DATE_PART('year', created_at) = DATE_PART('year', CURRENT_DATE)")->where('status', 2)->select('id')->get();
            $list["bulanIni"] = $countBulan->count();

            $countHari = Base64DokModel::whereRaw("DATE_PART('day', created_at) = DATE_PART('day', CURRENT_DATE)")->whereRaw("DATE_PART('year', created_at) = DATE_PART('year', CURRENT_DATE)")->where('status', 2)->select('id')->get();
            $list["hariIni"] = $countHari->count();

            $gagal = Base64DokModel::where('status', 3)->select('id')->get();
            $list["gagal"] = $gagal->count();

            $antrian = Base64DokModel::where('status', 1)->select('id')->get();
            $list["antrian"] = $antrian->count();
            
            $countGenerated = Meterai::all();
            $list["generated"] = $countGenerated->count();

            $countGagal = Meterai::whereRaw("DATE_PART('day', created_at) = DATE_PART('day', CURRENT_DATE)")->whereRaw("DATE_PART('year', created_at) = DATE_PART('year', CURRENT_DATE)")->where('status', 3)->select('id')->get();;
            $list["gagalMeterai"] = $countGagal->count();
                
            $list["version"] = config('app.version');

            DB::commit();
            return response(['code' => 0,'message' =>'Success', 'data' => $list]);
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }
}
