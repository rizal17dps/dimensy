<?php

namespace App\Helpers;
use App\Models\Sign;
use Illuminate\Support\Facades\Storage;

/**
 * Format response.
 */
class ResponseFormatter
{
    /**
     * API Response
     *
     * @var array
     */
    protected static $response = [
        'resultCode' => null,
        'dataId' => null,
        'fileName' => null,
        'serial_number' => null,
        'status' => null,
        'desc' => null,
        'base64Document' => null
    ];

    public static function getDocument($userId, $id) {
        $dok = Sign::with('meteraiView', 'descView')->where('users_id', (int)$userId)->where('id', (int)$id)->first();
        if($dok){
            $resultCode = 99;
            $base64 = '';

            if($dok->status_id == 1){
                $resultCode = 95;
            } else if($dok->status_id == 8) {
                $resultCode = 0;
                $base64 = base64_encode(Storage::disk('minio')->get($dok->user->company_id .'/dok/' . $dok->users_id . '/' . $dok->name));
            } else if($dok->status_id == 9) {
                $resultCode = 97;
            }

            self::$response['resultCode'] = $resultCode;
            self::$response['dataId'] = $dok->id;
            self::$response['fileName'] = $dok->realname;
            self::$response['serial_number'] = $dok->meteraiView[0]->serial_number ?? '';
            self::$response['status'] = $dok->stat->name;
            self::$response['desc'] = $dok->DescView[0]->desc;
            self::$response['base64Document'] = $base64;
            return self::$response;
        } else {
            self::$response['resultCode'] = 99;
            self::$response['desc'] = 'Data tidak ditemukan';
            return self::$response;
        }
    }

    public static function get_client_ip() {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
           $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = \Request::ip();
        return $ipaddress;
    }
}
