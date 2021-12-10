<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CekCredential;
use App\Services\Utils;
use App\Services\MeteraiService;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class MeteraiController extends Controller
{
    //
    public function __construct(CekCredential $cekCredential, Utils $utils, MeteraiService $meterai){
        $this->cekCredential = $cekCredential;
        $this->utils = $utils;
        $this->meterai = $meterai;
    }

    public function jenisDok(Request $request) {
        try{
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email Required']);
            }
            
            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            }  else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                $user = User::where('email', $email)->first();
                if($user){
                    $jenisDok = DB::table('jenis_dokumen')->select('id', 'nama')->get();
                    DB::commit();
                    return response(['code' => 0, 'data' => $jenisDok ,'message' =>'Success']);
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }  
    }

    public function signingMeterai(Request $request) {
        try{
            $header = $request->header('apiKey');
            $email = $request->header('email');

            if(!$header){
                return response(['code' => 98, 'message' => 'Api Key Required']);
            }

            if(!$email){
                return response(['code' => 98, 'message' => 'Email Required']);
            }
            
            $cekToken = $this->cekCredential->cekToken($header);
            $cekEmail = $this->cekCredential->cekEmail($header, $email);
            if(!$cekToken){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'apiKey Mismatch']);
            }  else if(!$cekEmail){
                DB::rollBack();
                return response(['code' => 98, 'message' => 'Email Not Found']);
            } else {
                $user = User::where('email', $email)->first();
                if($user){
                    $request->validate([
                        'content' => 'required|array',
                        'content.filename' => 'required',
                        'content.noDoc' => 'required',
                        'content.tglDoc' => 'required',
                        'content.base64Doc' => 'required',
                        'content.docpass' => 'required',
                        'content.signer' => 'array|min:1',
                        'content.signer.lowerLeftX' => 'required',
                        'content.signer.lowerLeftY' => 'required',
                        'content.signer.upperRightX' => 'required',
                        'content.signer.upperRightY' => 'required',
                        'content.signer.page' => 'required|numeric',
                        'content.signer.location' => 'string|regex:/^[a-zA-Z]+$/u',
                        'content.signer.docType' => 'string|max:255',
                    ]);

                    $paramsSn = [
                        "isUpload"=> false,
                        "namadoc"=> $request->input('namaDoc'),
                        "namafile"=> $doks->realname,
                        "nilaidoc"=> "10000",
                        "snOnly"=> false,
                        "nodoc"=> "34",
                        "tgldoc"=> date_format($doks->updated_at,"Y-m-d")
                    ];
                    
                    $generateSn = $this->meterai->callAPI('chanel/stampv2', $paramsSn, 'stamp', 'POST');
                    if($generateSn["statusCode"] == "00"){
                        $image_base64 = base64_decode($serialNumber["result"]["image"]);
                        $fileName = $serialNumber["result"]["sn"].'.png';
                        Storage::disk('minio')->put($doks->user->company_id .'/dok/'.$doks->users_id.'/meterai/'.$fileName, $image_base64);
                        
                        $meterai = new Meterai();
                        $meterai->serial_number = $serialNumber["result"]["sn"];
                        $meterai->path = $doks->user->company_id .'/dok/'.$doks->users_id.'/meterai/'.$fileName;
                        $meterai->status = 0;
                        $meterai->company_id = auth()->user()->company_id;
                        $meterai->save();
                        
                        DB::commit();
                        return response(['code' => 0, 'data' => $jenisDok ,'message' =>'Success']);
                    } else {
                        DB::rollBack();
                        return response(['code' => 97, 'message' => 'Error Generate Serial Number']);
                    }                   
                    
                } else {
                    DB::rollBack();
                    return response(['code' => 98, 'message' => 'Email Not Found']);
                }
            }
        } catch(\Exception $e) {
            return response(['code' => 99, 'message' => $e->getMessage()]);
        }  
    }
}
