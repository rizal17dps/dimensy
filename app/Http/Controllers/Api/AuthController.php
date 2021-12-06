<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    //
    public function login(Request $request) {
        // $loginData = $request->validate([
        //     'email' => 'email|required',
        //     'password' => 'required'
        // ]);

        if (!auth()->attempt($loginData)) {
            return response(['message' => 'Invalid Credentials']);
        }

        // $accessToken = auth()->user()->createToken('authToken')->accessToken;
        $accessToken = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiMzQxYmU3M2MxMmIxZjRkMDk0YjAwN2Q1ODk5ODIyOTMwMTM5YzFjZjNiOTNjZWNjMDU0ZGVjNGQyMDk4Y2EzNzc3OGI0ZDkxYTFlOWJlZTQiLCJpYXQiOjE2Mzg1MTQxMTMuNzQ3NTQ0LCJuYmYiOjE2Mzg1MTQxMTMuNzQ3NTQ4LCJleHAiOjE2NzAwNTAxMTMuNjQ3Mzc5LCJzdWIiOiIxMzAiLCJzY29wZXMiOltdfQ.nijDL856PKIiVANSPoQW6UbzFUhapdE5ie9MhYyRm5QxQkUbyfbn5hDNMrYZ_2MU3uKv6T0Tv7VLDQOPoxcvUquQnl6OpmOcY30Qpnarj9D3Brnq5Jmv8DaHFf1Z9uPniH01ACDpEDuy72TNA8dR3utBGqJuxeRgyUA6ptB1tabvIvh2CW0lXg2LcPRQ9Y5yCd42nH5WybwPI2wh07TH4GZNeeIeAWcbXVSAkuVz9ngyUjThLCoPWgh2dnkearkr9ZudG-msNYj_l6iggjxV7ALCvwnGGUY22hwkGgWipEeaTC0d3kBLSUnIsbX_Ax6Fd099Mtw_eIH7Q-9ylp_kc7vCm9Mm7CKlbCiefHxVQtwafyW8v3tWpE28uR56RpT7Ey1SHFkTYTMwFRW_5CrFt83JCpBlkcm2B4Rr3ifLilkDJWRQJU3IkMDehQ_i11HwMURNsMoweR71qgCcLBJF27zpwm7hMQF6Ze9yTE9rmmw1NxrhSLpqA8hECRjzc99oDB2Nl3RM66Bm3id62If01pilZr8Cxtlfn1xzRjG5FnekFa8HKjuUslIW_0tJ013bXUmi4R-UDGRbfrx7MtRPC_TPP-zs1_znfKpwYxIv_cAf50T1V-hOIn8Z_v0ELN1mjn87hPlXEPX7YuHbHJ9z9fzSVk75Un0e-r_lJrGLfNY";

        return response(['code' => 200, 'token' => $accessToken, 'message' => 'Login Success']);

    }
}
