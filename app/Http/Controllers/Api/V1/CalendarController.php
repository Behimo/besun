<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Calendar\CalendarService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        protected CalendarService $calendar,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->input('from'));
        $to = Carbon::parse($request->input('to'));

        return response()->json([
            'events' => $this->calendar->events($request->user(), $from, $to),
        ]);
    }
}
