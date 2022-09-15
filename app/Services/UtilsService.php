<?php

namespace App\Services;

use GuzzleHttp\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\AuthModel;

class UtilsService
{

    public function __construct(Client $client)
    {
        $this->url = config('app.DIMENSY_UTILS'); //'https://apgdev.peruri.co.id:9044/gateway/';
        $this->http = $client;
    }

    private function getResponse(string $uri = null, array $params = [], $token = null)
    {
        try {
            $full_path = $this->url;
            $full_path .= $uri;
            $head = [
                'content-type' => 'application/json',
            ];

            $request = $this->http->request('POST', $full_path, [
                'headers' => $head,
                'json' => $params,
                'verify' => false,
            ]);
            
            $response = $request ? $request->getBody()->getContents() : null;
            $status = $request ? $request->getStatusCode() : 500;
            if ($response && $status === 200 && $response !== 'null') {
                return json_decode($response, true);
            }

        } catch (\ClientErrorResponseException $e) {
            return $e->getResponse()->getBody(true);
        } catch (ConnectException $e) {
            // Connection exceptions are not caught by RequestException
            $x['resultCode'] = "408";
            $x['resultDesc'] = $e->getMessage();
            return $x;
            //die;
        } catch (RequestException $e) {
            // Connection exceptions are not caught by RequestException
            //Log::error($e->getResponse());
            $x['resultCode'] = "500";
            $x['resultDesc'] = "API Gateway encountered an error";
            return $x;
            //dd($e->getResponse());
        }
    }

    public function callAPI(string $uri = null, array $params = [])
    {
        try{            
            $x = $this->getResponse(
                $uri,$params
            );
            
            //$this->logError(\Request::getClientIp(), $uri, json_encode($params), json_encode($x) , 'HASIL');
            
            return $x;
        } catch (\Exception $e) {
            //$this->logError(\Request::getClientIp(), $uri, json_encode($params), $e->getMessage() , 'GAGAL');
            return $e->getMessage();
        }
    }

    // public function logError($ip, $url, $param, $desc, $stat){
    //     try{
    //         if(isset(auth()->user()->id)){
    //             $me = auth()->user()->id;
    //         } else {
    //             $me = "guest";
    //         }
    //         $log = new LogModel();
    //         $log->ip = $ip;
    //         $log->users_id = $me;
    //         $log->url = $url;
    //         $log->param = $param;
    //         $log->desc = $desc;
    //         $log->status = $stat;
    //         $log->save();
    //     } catch(\Exception $e) {
    //         Log::error('Log Error '.$e->getMessage());
    //         return true;
    //     }
    // }

}
