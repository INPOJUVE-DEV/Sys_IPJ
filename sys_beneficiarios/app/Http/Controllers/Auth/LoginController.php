<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        if (! Auth::guard('web')->attempt($credentials)) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Invalid credentials.',
                'instance' => $request->fullUrl(),
            ], 401, ['Content-Type' => 'application/problem+json']);
        }

        /** @var \App\Models\User $user */
        $user = Auth::guard('web')->user();
        $token = $user->createToken('admin');

        return response()->json([
            'token_type' => config('sanctum.token_prefix', 'Bearer'),
            'token' => $token->plainTextToken,
        ]);
    }
}
