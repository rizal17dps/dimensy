<?php

namespace App\Services;

use GuzzleHttp\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\AuthModel;
use App\Models\LogModel;

class MeteraiService
{

    public function __construct(Client $client)
    {
        $this->urlInfo = "https://backendservicedev.scm.perurica.co.id/"; //'https://backendservicedev.scm.perurica.co.id/';
        $this->urlStamp = "https://stampv2dev.scm.perurica.co.id/"; //'https://stampv2dev.scm.perurica.co.id/';
        $this->urlKeyStamp = "http://192.168.200.205:8888/"; //'http://192.168.200.205:8888/';
        $this->http = $client;
    }

    private function getResponse(string $uri = null, array $params = [], $type = "info", $method = "POST", $token = null)
    {
        try {
            if($type == "info"){
                $full_path = $this->urlInfo;
            } elseif ($type == "stamp") {
                $full_path = $this->urlStamp;
            } elseif($type == "keyStamp") {
                $full_path = $this->urlKeyStamp;
            }

            $full_path .= $uri;
            if ($token != null) {
                $head = [
                    'content-type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ];
            } else {
                $head = [
                    'content-type' => 'application/json',
                ];
            }
            
            $request = $this->http->request($method, $full_path, [
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
            $x['statusCode'] = "408";
            $x['message'] = $e->getResponse()->getBody(true);
            return $x;
        } catch (ConnectException $e) {
            // Connection exceptions are not caught by RequestException
            $x['statusCode'] = "408";
            $x['message'] = $e->getMessage();
            return $x;
            //die;
        } catch (RequestException $e) {
            // Connection exceptions are not caught by RequestException
            //Log::error($e->getResponse());
            $x['statusCode'] = "500";
            $x['message'] = "API Gateway encountered an error";
            return $x;
            //dd($e->getResponse());
        }
    }

    public function getJwt()
    {
        return $this->getResponse(
                'api/users/login',
                [
                    "user" => "almufadhdhal@gmail.com",
                    "password" => "qwerty123",
                ]
            );
      
    }

    public function callAPI(string $uri = null, array $params = [], $type, $method)
    {
        try{
            $cek = $this->getJwt();
            if ($cek['statusCode'] == "00") {
                $x = $this->getResponse(
                    $uri,$params,$type,$method,$cek['token']
                );
                $x['token'] = $cek['token'];
            }  else {
                $x = $cek;
            }
            return $x;
        } catch (\Exception $e) {
            //$this->logError(\Request::getClientIp(), $uri, json_encode($params), $e->getMessage() , 'GAGAL');
            return $e->getMessage();
        }
    }

    public function logError($ip, $url, $param, $desc, $stat){
        try{
            if(isset(auth()->user()->id)){
                $me = auth()->user()->id;
            } else {
                $me = "guest";
            }
            $log = new LogModel();
            $log->ip = $ip;
            $log->users_id = $me;
            $log->url = $url;
            $log->param = $param;
            $log->desc = $desc;
            $log->status = $stat;
            $log->save();
        } catch(\Exception $e) {
            Log::error('Log Error '.$e->getMessage());
            return true;
        }
    }

}
