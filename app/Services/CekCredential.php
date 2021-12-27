<?php

namespace App\Services;
use App\Models\AuthClientModel;
use App\Models\User;

class CekCredential
{

    public function cekToken($token)
    {
        $auth = AuthClientModel::where('token', $token)->first();
        dd($auth);
        if($auth){
            return $auth->company_id;
        } else {
            return false;
        }
    }

    public function cekEmail($token, $email)
    {
        $auth = AuthClientModel::where('token', $token)->first();
        $user = User::where('email', $email)->where('is_active', 'true')->first();
        if($auth && $user){
            if($auth->company_id == $user->company_id){
                return $user;
            } else {
                return false;
            }
            
        } else {
            return false;
        }
    }

}
