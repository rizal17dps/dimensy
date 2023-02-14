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
use App\Models\Status;
use App\Models\PaketDetail;
use App\Models\Company;
use App\Helpers\ResponseFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class QuotaController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils, CompanyService $companyService){
        $this->cekCredential = $cekCredential;
        $this->companyService = $companyService;
        $this->utils = $utils;
        set_time_limit(config('app.MAX_EXECUTION_TIME'));
    }

    public function cekQuota(Request $request) {
        $start = microtime(true);
        DB::beginTransaction();
        try{
            config(['logging.channels.api_log.path' => storage_path('logs/api/dimensy-'.date("Y-m-d H").'.log')]);
            if($this->utils->block()){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".ResponseFormatter::get_client_ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id  Response time: ".$time_elapsed_secs);
                return response(['code' => 99, 'message' => 'Sorry, your IP was blocked due to suspicious access, please contact administrator info@dimensy.id']);
            }

            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".ResponseFormatter::get_client_ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Api Key Required  Response time: ".$time_elapsed_secs);
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".ResponseFormatter::get_client_ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Email Required  Response time: ".$time_elapsed_secs);
                return response(['code' => 98, 'message' => 'Email Required']);
            }

            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                $this->utils->logBruteForce(\Request::getClientIp(), $header, $email);
                DB::commit();
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".ResponseFormatter::get_client_ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - apiKey Mismatch  Response time: ".$time_elapsed_secs);
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            }  else if(!$cekEmail){
                DB::rollBack();
                $time_elapsed_secs = microtime(true) - $start;
                Log::channel('api_log')->error("IP : ".ResponseFormatter::get_client_ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Email Not Found  Response time: ".$time_elapsed_secs);
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
                    $time_elapsed_secs = microtime(true) - $start;
                    Log::channel('api_log')->info("IP : ".ResponseFormatter::get_client_ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Success  Response time: ".$time_elapsed_secs);
                    return response(['code' => 0, 'data' => $list ,'message' =>'Success']);
                } else {
                    DB::rollBack();
                    $time_elapsed_secs = microtime(true) - $start;
                    Log::channel('api_log')->error("IP : ".ResponseFormatter::get_client_ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - Email Not Found  Response time: ".$time_elapsed_secs);
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }
            }
        } catch(\Exception $e) {
            $time_elapsed_secs = microtime(true) - $start;
            Log::channel('api_log')->error("IP : ".ResponseFormatter::get_client_ip()." EndPoint : ".url()->current()." Email: ".$email." Status : Error - ".$e->getMessage()."  Response time: ".$time_elapsed_secs);

            DB::rollBack();
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

            $pricing = PricingModel::where('name_id', $request->input('paket'))->where('company_id', 657)->first();
            if(!$pricing){
                $pricing = new PricingModel();
            }
            $pricing->company_id = 657;
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
            $mapCompany = MapCompany::where('company_id', 657)->first();
            if($mapCompany){
                $paketId = $mapCompany->paket_id;
            } else {
                $paket = new Paket();
                $paket->name = 'Paket-657';
                $paket->icon = 'no-icon';
                $paket->durasi = '1';
                $paket->satuan = 'Tahun';
                $paket->company_id = 657;
                $paket->save();

                $storeMapCompany = new MapCompany();
                $storeMapCompany->paket_id = $paket->id;
                $storeMapCompany->company_id = 657;
                $storeMapCompany->expired_date = $dateExp;
                $storeMapCompany->save();

                $paketId = $paket->id;
            }

            $paketDetail = PaketDetail::where('detail_name_id', $request->input('paket'))->where('company_id', 657)->first();
            if(!$paketDetail){
                $paketDetail = new PaketDetail();
            }
            $paketDetail->value = $request->input('qty');
            $paketDetail->satuan = $infoDetil->satuan;
            $paketDetail->type = $infoDetil->type;
            $paketDetail->company_id = 657;
            $paketDetail->detail_name_id = $request->input('paket');
            $paketDetail->save();

            $mapPaket = new MapPaket();
            $mapPaket->paket_id = $paketId;
            $mapPaket->paket_detial_id = $paketDetail->id;
            $mapPaket->save();

            $quota = Quota::where('company_id', 657)->where('paket_detail_id', $paketDetail->id)->first();
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
                $quota->company_id = 657;
                if($infoDetil->type == 'storage'){
                    $quota->quota = $request->input('qty') * pow(1024, 3);
                } else {
                    $quota->quota = $request->input('qty');
                }
                $quota->all = $request->input('qty');
            }

            $quota->save();

            DB::commit();
            return response(['code' => 0,'message' =>'Success']);
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function updateExpiredDate(Request $request) {
        $cekUser = User::where('email', $request->input('email'))->first();
        $dt1 = new DateTime();
        $today = $dt1->format("Y-m-d");

        $dt2 = new DateTime("+ 1 year");
        $dateExp = $dt2->format("Y-m-d");
        $storeMapCompany = MapCompany::where('company_id', $cekUser->company_id)->first();
        $storeMapCompany->expired_date = $dateExp;
        $storeMapCompany->save();
        return response(['code' => 0,'message' =>'Success']);
    }

    public function monitor(){
        DB::beginTransaction();
        try{
            $list = [];

            $countBulan = Base64DokModel::whereRaw("DATE_PART('month', created_at) = DATE_PART('month', CURRENT_DATE)")->whereRaw("DATE_PART('year', created_at) = DATE_PART('year', CURRENT_DATE)")->where('status', 2)->select('id')->get();
            $list["bulanIni"] = $countBulan->count();

            $countHari = Base64DokModel::whereRaw("DATE(created_at) = CURRENT_DATE")->where('status', 2)->select('id')->get();
            $list["hariIni"] = $countHari->count();

            $gagal = Base64DokModel::whereRaw("DATE_PART('month', created_at) = DATE_PART('month', CURRENT_DATE)")->whereRaw("DATE_PART('year', created_at) = DATE_PART('year', CURRENT_DATE)")->where('status', 3)->select('id')->get();
            $list["gagal"] = $gagal->count();

            $antrian = Base64DokModel::where('status', 1)->select('id')->get();
            $list["antrian"] = $antrian->count();

            $countGenerated = Meterai::where('status', 0)->select('id')->get();
            $list["generated"] = $countGenerated->count();

            $countGagal = Base64DokModel::whereRaw("DATE(created_at) = CURRENT_DATE")->where('status', 3)->select('id')->get();;
            $list["gagalMeterai"] = $countGagal->count();

            $countGagalAll = Meterai::whereIn('status', ['3', '4'])->select('id')->get();;
            $list["countGagalAll"] = $countGagalAll->count();

            $countBerhasilAll = Meterai::where('status', 1)->select('id')->get();;
            $list["countBerhasilAll"] = $countBerhasilAll->count();

            $list["version"] = config('app.version');

            DB::commit();
            return response(['code' => 0,'message' =>'Success', 'data' => $list]);
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function cekGagalStamp(){
        DB::beginTransaction();
        try{
            $data = Meterai::where("status", 3)->get();

            return response(['code' => 0,'message' =>'Success', 'data' => $data]);
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function cekDokGagal() {
        $a = Base64DokModel::where('status', 3)->select('id')->get();
        return response(['code' => 0,'message' =>'Success', 'data' => $a]);
    }

    public function insertStatus($id) {
        $a = Sign::find($id);
        $a->status_id = 9;
        $a->save();
        return response(['code' => 0,'message' =>'Success']);
    }

    public function invalidSerialNumber(Request $request) {
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
                $image_base64 = base64_decode($request->input('base64'));
                $userId = User::where('email', $request->input('email'))->first();
                if($userId){
                    $fileName = $request->input('sn').'.png';
                    Storage::disk('minio')->put($userId->company_id.'/dok/'.$userId->id.'/meterai/'.$fileName, $image_base64);

                    $insertMeterai = Meterai::where('serial_number', $request->input('sn'))->first();
                    if(!$insertMeterai){
                        $insertMeterai = new Meterai();
                        $insertMeterai->serial_number = $request->input('sn');
                        $insertMeterai->path = $userId->company_id.'/dok/'.$userId->id.'/meterai/'.$fileName;
                        $insertMeterai->status = 0;
                        $insertMeterai->company_id = $userId->company_id;
                        $insertMeterai->save();
                    }

                    return response(['code' => 0, 'message' => 'Sukses']);
                } else {
                    return response(['code' => 1, 'message' => 'User tidak ditemukan']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function updateInvalidSerialNumber(Request $request) {
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
                $userId = User::where('email', $request->input('email'))->first();
                if($userId){

                    $updateMeterai = Meterai::where('serial_number', $request->input('sn'))->first();
                    if($updateMeterai){
                        $updateMeterai->status = 4;
                        $updateMeterai->desc = "Sudah dikembalikan ke DPS";
                        $updateMeterai->save();

                        DB::commit();

                        return response(['code' => 0, 'message' => 'Sukses']);
                    } else {
                        return response(['code' => 2, 'message' => 'Meterai tidak ditemukan']);
                    }

                } else {
                    return response(['code' => 1, 'message' => 'User tidak ditemukan']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function cekUsedSN(Request $request) {
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
                $meterai = Meterai::where('company_id', $request->input('company_id'))->where('status', 1)->get();
                return response(['code' => 0, 'message' => 'Sukses', "total" => $meterai->count(), "data" => $meterai]);
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function cekUnusedSN(Request $request) {
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
                $meterai = Meterai::where('company_id', $request->input('company_id'))->where('status', 0)->get();
                return response(['code' => 0, 'message' => 'Sukses', "total" => $meterai->count(), "data" => $meterai]);
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function baseQuota(Request $request) {
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
                $paketDetail = Quota::join('paket_detail', 'paket_detail.id', '=', 'quota_company.paket_detail_id')->where('detail_name_id', 6)->where('quota_company.company_id', $request->input('company_id'))->select('paket_detail.id')->first();
                $quotaCurrent = Quota::where('paket_detail_id', $paketDetail->id)->where('company_id', $request->input('company_id'))->first();
                $penggunaanMeterai = Meterai::where('company_id', $request->input('company_id'))->where('status', 1)->count();
                $kurangSeharusnya = $quotaCurrent->all - $penggunaanMeterai;
                if($kurangSeharusnya > $quotaCurrent->quota) {
                    $selisih = $kurangSeharusnya - $quotaCurrent->quota;

                    $quotaCurrent->quota = $quotaCurrent->quota + $selisih;
                    $quotaCurrent->save();

                    return response(['code' => 0, 'message' => 'Terdapat penambahan data', 'data' => $selisih]);
                } else if($kurangSeharusnya < $quotaCurrent->quota) {
                    $selisih = $quotaCurrent->quota - $kurangSeharusnya;
                    $quotaCurrent->quota = $quotaCurrent->quota - $selisih;
                    $quotaCurrent->save();

                    return response(['code' => 0, 'message' => 'Terdapat pengurangan data', 'data' => $selisih]);
                } else {
                    return response(['code' => 2, 'message' => 'tidak ada selisih']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }
    }

    public function cekCompanyId(Request $request) {
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
                $cekCompany = Company::select('id', 'name')->get();
                return response(['code' => 0, 'data' => $cekCompany]);
            }
    }

    public function loadTest() {
        $start = microtime(true);
        config(['logging.channels.api_log.path' => storage_path('logs/api/loop-'.date("Y-m-d H").'.log')]);
        for ($x = 0; $x < 1000000; $x++){
            $time_elapsed_secs = microtime(true) - $start;
            Log::channel('api_log')->error("IP : ".ResponseFormatter::get_client_ip()." EndPoint : ".url()->current()." Status : loop ke - ".$x."  Response time: ".$time_elapsed_secs);
        }
    }
}
