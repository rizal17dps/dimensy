<?php

namespace App\Services;

use GuzzleHttp\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\AuthModel;
use App\Models\LogModel;
use App\Services\DimensyService;

class MeteraiService
{

    public function __construct(Client $client, DimensyService $dimensyService)
    {
        $this->urlInfo = config('app.API_METERAI_LOGIN');
        $this->urlStamp = config('app.API_METERAI_STAMP');
        $this->urlKeyStamp = config('app.API_METERAI_KEYSTAMP');
        $this->http = $client;
        $this->dimensyService = $dimensyService;
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
            $x['errorCode'] = "408";
            $x['message'] = $e->getResponse()->getBody(true);
            Log::channel('sentry')->info("ERROR ".$e->getResponse()->getBody(true));
            return $x;
        } catch (ConnectException $e) {
            // Connection exceptions are not caught by RequestException
            $x['errorCode'] = "408";
            $x['message'] = $e->getMessage();
            Log::channel('sentry')->info("ERROR ".$e->getMessage());
            return $x;
            //die;
        } catch (RequestException $e) {
            // Connection exceptions are not caught by RequestException
            $x['errorCode'] = "500";
            $x['message'] = $e->getMessage();
            Log::channel('sentry')->info("ERROR ".$e->getMessage());
            return $x;
        }
    }

    public function callAPI(string $uri = null, array $params = [], $type, $method, $token = "")
    {
        try{
            
            $auth = AuthModel::where('id', 2)->whereDate('expired', '>', date("Y-m-d H:i:s"))->first();
            if($auth){
                $x = $this->getResponse(
                    $uri,$params,$type,$method,$auth->token
                );
                $x['data'] = $auth->token;
            } else {
                $cek = $this->dimensyService->callAPI('api/getJwt');
                if ($cek['code'] == "0") {
                    
                    AuthModel::truncate();
                    $auth = new AuthModel();
                    $auth->id = 2;
                    $auth->token = $cek['data'];
                    $auth->expired = $cek["expiredDate"];
                    $auth->created = date("Y-m-d H:i:s");
                    $auth->save();

                    $x = $this->getResponse(
                        $uri,$params,$type,$method,$cek['data']
                    );
                    $x['data'] = $cek['data'];
                }  else {                
                    $x = $cek;
                }
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
