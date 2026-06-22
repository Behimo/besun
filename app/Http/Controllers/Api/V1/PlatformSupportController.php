<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Platform\PlatformSupportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSupportController extends Controller
{
    public function __construct(
        protected PlatformSupportService $support,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json($this->support->dashboard());
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        return response()->json($this->support->search($data['q']));
    }
}
