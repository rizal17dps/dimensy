<?php

namespace App\Services;

use App\Models\HistoryPemakaian;
use App\Models\HistoryTransaksi;
use App\Models\Quota;
use App\Models\User;

use Illuminate\Support\Facades\DB;

class CompanyService
{

    public function history($paket, $user)
    {
        DB::beginTransaction();
        try{
            $history = new HistoryPemakaian();
            $history->paket_detail_id = $paket;
            $history->users_id = $user;
            $history->save();

            $me = User::find($user);
            
            $quota = Quota::where('paket_detail_id', $paket)->where('company_id', $me->company_id)->first();
            if($quota){
                $quota->quota = $quota->quota - 1;
                $quota->save();
            }
            
            DB::commit();
            return true;
        } catch(\Exception $e) {
            DB::rollBack();
            return false;
        }        
    }

    public function cek($paket, $user){
        try{ 
            $me = User::find($user);           
            $quota = Quota::where('paket_detail_id', $paket)->where('company_id', $me->company_id)->first();
            if($quota){
                if($quota->quota <= 0){
                    return true;
                } else {
                    return false;
                }
            }      
        } catch(\Exception $e) {
            return false;
        }
    }

    public function delete($paket){
        try{
            $quota = Quota::where('paket_detail_id', $paket)->where('company_id', auth()->user()->company_id)->first();
            if($quota){
                $quota->quota = $quota->quota + 1;
                $quota->save();
            } else {
                dd($paket);  
            }
             
        } catch(\Exception $e) {
            return false;
        }
    }

    public function historyTransaksi($company, $paket, $desc, $type)
    {
        DB::beginTransaction();
        try{
            $history = new HistoryTransaksi();
            $history->company_id = $company;
            $history->paket_id = $paket;
            $history->description = $desc;
            $history->type = $type;
            $history->save();
            
            DB::commit();
            return true;
        } catch(\Exception $e) {
            DB::rollBack();
            return false;
        }        
    }

}
