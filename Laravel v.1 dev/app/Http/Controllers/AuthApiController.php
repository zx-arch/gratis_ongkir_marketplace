<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\API\RegisterRequest as RegisterApiRequests;

class AuthApiController extends Controller
{
    public function register(RegisterApiRequests $request)
    {
        $validatedData = $request->validated();

        try {
            $password = $validatedData['password'];

            //kondisi jika password belum di-hash
            if (Hash::needsRehash($password)) {
                $password = Hash::make($password);
            }

            // Buat pengguna baru
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => $password,
            ]);

            $response = ['status' => 'success', 'data' => $user];

            return response()->json($response, 200);

        } catch (\Throwable $e) {

            return response()->json([
                'status' => 'error',
                'message' => "Failed to register user: {$e->getMessage()}",
            ], $e->getCode() ?? 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 9999999
        ]);
    }

    public function logout()
    {
        auth()->logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out'
        ], 200);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

    public function refresh()
    {
        return $this->respondWithToken(JWTAuth::parseToken()->refresh());
    }
}