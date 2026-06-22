<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Marketing\ContactLeadRequest;
use App\Infrastructure\Persistence\Eloquent\Models\MarketingLead;
use Illuminate\Http\JsonResponse;

class MarketingLeadController extends Controller
{
    public function store(ContactLeadRequest $request): JsonResponse
    {
        MarketingLead::create($request->validated());

        return response()->json(['message' => 'پیام شما با موفقیت ثبت شد.'], 201);
    }
}
