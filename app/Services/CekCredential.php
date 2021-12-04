<?php

namespace App\Services;
use App\Models\AuthClientModel;

class CekCredential
{

    public function cekToken($token)
    {
        $auth = AuthClientModel::where('token', $token)->first();
        if($auth){
            return $auth->company_id;
        } else {
            return false;
        }
    }

}
