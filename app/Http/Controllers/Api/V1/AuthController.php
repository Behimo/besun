<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SendOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Infrastructure\Services\Auth\OtpAuthService;
use App\Infrastructure\Services\AuthPayloadService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthPayloadService $authPayload,
        protected OtpAuthService $otpAuth,
    ) {}

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        return response()->json(
            $this->otpAuth->send($request->validated('phone'))
        );
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->otpAuth->verify(
            $request->validated('phone'),
            $request->validated('code'),
        );

        return $this->tokenResponse($user);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->authPayload->payload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'خروج با موفقیت انجام شد.']);
    }

    protected function tokenResponse(User $user): JsonResponse
    {
        if ($user->in_tenant_shell) {
            $user->update(['in_tenant_shell' => false]);
            $user = $user->fresh();
        }

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'accessToken' => $token,
            ...$this->authPayload->payload($user),
        ]);
    }
}
