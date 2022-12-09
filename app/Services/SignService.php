<?php

namespace App\Services;

use GuzzleHttp\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\AuthModel;

class SignService
{

    public function __construct(Client $client)
    {
        $this->url = env('API_PERURI'); 
        $this->http = $client;
    }

    private function getResponse(string $uri = null, array $params = [], $token = null)
    {
        try {
            $full_path = $this->url;
            $full_path .= $uri;
            if ($token != null) {
                $head = [
                    'content-type' => 'application/json',
                    'x-Gateway-APIKey' => env('APIKey_PERURI'),
                    'Authorization' => 'Bearer ' . $token,
                ];
            } else {
                $head = [
                    'content-type' => 'application/json',
                    'x-Gateway-APIKey' => env('APIKey_PERURI'),
                ];
            }

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
        } catch (RequestException $e) {
            // Connection exceptions are not caught by RequestException
            $x['resultCode'] = "500";
            $x['resultDesc'] = $e->getResponse();
            return $x;
        }
    }

    public function getJwt()
    {
        return $this->getResponse(
            env('JWT').'/1.0/getJsonWebToken/v1',
                [
                    "param" => [
                        "systemId" => env('SYSTEMID'),
                    ],
                ]
            );
    }

    public function callAPI(string $uri = null, array $params = [])
    {
        try{
            
            $auth = AuthModel::where('id', 1)->whereDate('expired', '>', date("Y-m-d H:i:s"))->first();
            if($auth){
                $x = $this->getResponse(
                    $uri,$params,$auth->token
                );
            } else {
                $cek = $this->getJwt();
                if ($cek['resultCode'] == "0") {
                    $x = $this->getResponse(
                        $uri,$params,$cek['data']["jwt"]
                    );
                    
                    AuthModel::truncate();
                    $auth = new AuthModel();
                    $auth->token = $cek['data']["jwt"];
                    $auth->expired = $cek['data']["expiredDate"];
                    $auth->created = date("Y-m-d H:i:s");
                    
                    $auth->save();
                } else {
                    $x = $cek;
                }
            }
            
            return $x;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

}
