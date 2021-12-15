<?php

namespace App\Services;

use DateTime;
use Illuminate\Support\Facades\Crypt;

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
}
