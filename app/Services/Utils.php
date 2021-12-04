<?php

namespace App\Services;

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
}
