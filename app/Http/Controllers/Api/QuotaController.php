<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CekCredential;
use App\Services\Utils;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuotaController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
    }

    public function cekQuota(Request $request) {
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
                $this->utils->logBruteForce(\Request::ip(), $header, $email);
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
}
