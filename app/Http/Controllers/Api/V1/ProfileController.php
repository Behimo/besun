<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\User\UserProfileService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        protected UserProfileService $profiles,
    ) {}

    public function me(Request $request): JsonResponse
    {
        return response()->json(
            $this->profiles->getSelfProfile($request->user()),
        );
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'visible_to_owners' => ['sometimes', 'boolean'],
        ]);

        $profile = $this->profiles->updateSelfProfile($request->user(), $data);

        return response()->json($profile);
    }
}
