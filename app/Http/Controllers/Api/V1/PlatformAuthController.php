<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Platform\PlatformStaffAuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\PlatformLoginRequest;
use App\Infrastructure\Services\PlatformStaffPayloadService;
use App\Models\PlatformStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAuthController extends Controller
{
    public function __construct(
        protected PlatformStaffAuthService $auth,
        protected PlatformStaffPayloadService $payload,
    ) {}

    public function login(PlatformLoginRequest $request): JsonResponse
    {
        $staff = $this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('portal'),
        );

        $token = $staff->createToken('platform-spa')->plainTextToken;

        return response()->json([
            'accessToken' => $token,
            ...$this->payload->payload($staff),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var PlatformStaff $staff */
        $staff = $request->user();

        return response()->json($this->payload->payload($staff));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'خروج با موفقیت انجام شد.']);
    }
}
