<?php

namespace App\Services;

use GuzzleHttp\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\AuthModel;

class DimensyService
{

    public function __construct(Client $client)
    {
        $this->url = config('app.API_DIMENSY');
        $this->http = $client;
    }

    private function getResponse(string $uri = null, array $params = [], $token = null)
    {
        try {
            $full_path = $this->url;
            $full_path .= $uri;
            $head = [
                'content-type' => 'application/json',
                'apiKey' => config('app.APIKEY'),
                'email' => config('app.EMAIL'),
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
            $x['code'] = "408";
            $x['message'] = $e->getMessage();
            return $x;
            //die;
        } catch (RequestException $e) {
            // Connection exceptions are not caught by RequestException
            //Log::error($e->getResponse());
            $x['code'] = "500";
            $x['message'] = $e->getResponse();
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

            return $x;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

}
