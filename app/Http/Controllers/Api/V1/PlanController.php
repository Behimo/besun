<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Plan;
use App\Infrastructure\Persistence\Eloquent\Models\PlanModule;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'plans' => Plan::where('is_active', true)->orderBy('price')->get(),
            'modules' => PlanModule::where('is_active', true)->orderBy('price')->get(),
        ]);
    }
}
