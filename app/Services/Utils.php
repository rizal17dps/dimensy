<?php

namespace App\Services;

use DateTime;
use Illuminate\Support\Facades\Crypt;
use App\Models\BruteModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Utils
{
    public function getBase64FileSize($base64) {
        try{
            $size_in_bytes = (int) (strlen(rtrim($base64, '=')) * 3 / 4);
            $size_in_kb    = $size_in_bytes / 1024;
            $size_in_mb    = $size_in_kb / 1024;
    
            return $size_in_mb;
        }
        catch(Exception $e){
            return $e;
        }
    }

    public function cekExpired($date){
        $date1 = new DateTime($date);
        $date2 = new DateTime("now");
        if($date1 <= $date2){
            return true;
        } else {
            return false;
        }
    }

    public function enkrip($str)
    {
        return Crypt::encryptString($str);;
    }

    public function dekrip($str)
    {
        return Crypt::decryptString($str);;
    }

    public function logBruteForce($ip, $token, $email){
        try{
            $log = new BruteModel();
            $log->ip_address = $ip;
            $log->token = $token;
            $log->email = $email;
            $log->save();
        } catch(\Exception $e) {
            Log::error('Log Error '.$e->getMessage());
            return true;
        }
    }

    public function block() {
        $ip = \Request::getClientIp();
        $count = BruteModel::where("created_at", ">=", date("Y-m-d H:i:s", strtotime("-1 hours")))->where("ip_address", $ip)->get();
        if($count->count() >= 10){
            return true;
        } else {
            $cek = DB::select("SELECT count(*) FROM brute_force WHERE created_at BETWEEN 
                                (SELECT MAX(created_at) FROM brute_force WHERE ip_address = '".$ip."') - INTERVAL '24 HOURS' 
                                AND (SELECT MAX(created_at) FROM brute_force WHERE ip_address = '".$ip."') + INTERVAL '24 HOURS' AND ip_address ='".$ip."'");
            if(empty($cek) || $cek[0]->count <= 10){
                return false;
            } else {
                return true;
            }
        }
    }
}
