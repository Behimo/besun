<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\DailyWork\DailyWorkReportService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\DailyWorkReportResource;
use App\Infrastructure\Persistence\Eloquent\Models\DailyWorkReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyWorkReportController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected DailyWorkReportService $reports,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('daily_reports.read');

        $paginator = $this->reports->list($request->user(), $request);

        return DailyWorkReportResource::collection($paginator)->response();
    }

    public function today(Request $request): JsonResponse
    {
        $this->requirePermission('daily_reports.read');

        $report = $this->reports->getOrCreateToday($request->user());

        return response()->json([
            'report' => new DailyWorkReportResource($report),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('daily_reports.create');

        $data = $request->validate([
            'report_date' => ['required', 'date'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'entries' => ['nullable', 'array'],
            'entries.*.title' => ['required_with:entries', 'string', 'max:255'],
            'entries.*.description' => ['nullable', 'string', 'max:2000'],
            'entries.*.minutes' => ['nullable', 'integer', 'min:0'],
            'entries.*.effort_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'entries.*.task_id' => ['nullable', 'integer', 'exists:tasks,id'],
        ]);

        $report = $this->reports->create($request->user(), $data);

        return response()->json([
            'report' => new DailyWorkReportResource($report),
        ], 201);
    }

    public function show(Request $request, DailyWorkReport $dailyWorkReport): JsonResponse
    {
        $this->requirePermission('daily_reports.read');

        $report = $this->reports->show($request->user(), $dailyWorkReport);

        return response()->json([
            'report' => new DailyWorkReportResource($report),
        ]);
    }

    public function update(Request $request, DailyWorkReport $dailyWorkReport): JsonResponse
    {
        $this->requirePermission('daily_reports.create');

        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
            'entries' => ['nullable', 'array'],
            'entries.*.title' => ['required_with:entries', 'string', 'max:255'],
            'entries.*.description' => ['nullable', 'string', 'max:2000'],
            'entries.*.minutes' => ['nullable', 'integer', 'min:0'],
            'entries.*.effort_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'entries.*.task_id' => ['nullable', 'integer', 'exists:tasks,id'],
        ]);

        $report = $this->reports->update($request->user(), $dailyWorkReport, $data);

        return response()->json([
            'report' => new DailyWorkReportResource($report),
        ]);
    }

    public function submit(Request $request, DailyWorkReport $dailyWorkReport): JsonResponse
    {
        $this->requirePermission('daily_reports.submit');

        $report = $this->reports->submit($request->user(), $dailyWorkReport);

        return response()->json([
            'report' => new DailyWorkReportResource($report),
        ]);
    }

    public function review(Request $request, DailyWorkReport $dailyWorkReport): JsonResponse
    {
        $data = $request->validate([
            'manager_score' => ['required', 'integer', 'min:1', 'max:5'],
            'manager_feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = $this->reports->review($request->user(), $dailyWorkReport, $data);

        return response()->json([
            'report' => new DailyWorkReportResource($report),
        ]);
    }

    public function performance(Request $request): JsonResponse
    {
        $month = $request->input('month');

        if ($month && ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            abort(422, 'فرمت ماه نامعتبر است.');
        }

        return response()->json([
            'month' => $month ?? now()->format('Y-m'),
            'rows' => $this->reports->monthlyPerformance($request->user(), $month),
        ]);
    }

    public function destroy(Request $request, DailyWorkReport $dailyWorkReport): JsonResponse
    {
        $this->reports->delete($request->user(), $dailyWorkReport);

        return response()->json(['message' => 'Deleted.']);
    }
}
