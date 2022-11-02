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

    /**
     * Give success response.
     */
    public static function success($data = null, $message = null, $total_data = null)
    {
        self::$response['meta']['message'] = $message;
        self::$response['data'] = $data;
        self::$response['total_data'] = $total_data;

        return response()->json(self::$response);
    }

    /**
     * Give error response.
     */
    public static function error($data = null, $message = null, $code = 99)
    {
        self::$response['meta']['status'] = 'error';
        self::$response['meta']['code'] = $code;
        self::$response['meta']['message'] = $message;
        self::$response['data'] = $data;

        return response()->json(self::$response);
    }

    public static function getDocument($userId, $id) {
        $dok = Sign::with('meteraiView', 'descView', 'approver')->where('users_id', (int)$userId)->where('id', (int)$id)->first();
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
            return ['desc' => "Dokumen Tidak Ditemukan"];
        }
    }
}
