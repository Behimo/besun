<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $wonStageIds = PipelineStage::where('type', 'sales')->where('is_won', true)->pluck('id');
        $totalDeals = Deal::count();
        $wonDeals = $wonStageIds->isNotEmpty()
            ? Deal::whereIn('pipeline_stage_id', $wonStageIds)->count()
            : 0;
        $totalLeads = Lead::count();
        $convertedLeads = Lead::where('status', 'converted')->count();
        $totalContacts = Contact::count();
        $pendingTasks = Task::where('status', 'pending')->count();
        $recentActivities = Activity::with('user')->latest('happened_at')->limit(10)->get();

        $myTasksQuery = Task::where('assignee_id', $user->id)
            ->where('status', '!=', 'completed');

        $myOverdueTasks = (clone $myTasksQuery)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $myDueToday = (clone $myTasksQuery)
            ->whereNotNull('due_at')
            ->whereDate('due_at', today())
            ->count();

        $myFollowUpsToday = Lead::where('assigned_to', $user->id)
            ->whereNotNull('next_follow_up_at')
            ->whereDate('next_follow_up_at', today())
            ->where('status', '!=', 'converted')
            ->count()
            + Deal::where('assigned_to', $user->id)
                ->whereNotNull('next_follow_up_at')
                ->whereDate('next_follow_up_at', today())
                ->count();

        $activeStageIds = PipelineStage::where('type', 'sales')
            ->where('is_won', false)
            ->where('is_lost', false)
            ->pluck('id');

        $salesByStage = PipelineStage::query()
            ->where('type', 'sales')
            ->withCount('deals')
            ->withSum('deals', 'amount')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'summary' => [
                'total_deals' => $totalDeals,
                'won_deals' => $wonDeals,
                'total_leads' => $totalLeads,
                'conversion_rate' => $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0,
                'total_contacts' => $totalContacts,
                'pending_tasks' => $pendingTasks,
                'my_overdue_tasks' => $myOverdueTasks,
                'my_due_today' => $myDueToday,
                'my_follow_ups_today' => $myFollowUpsToday,
                'total_revenue' => $wonStageIds->isNotEmpty()
                    ? Deal::whereIn('pipeline_stage_id', $wonStageIds)->sum('amount')
                    : 0,
                'active_pipeline_value' => $activeStageIds->isNotEmpty()
                    ? Deal::whereIn('pipeline_stage_id', $activeStageIds)->sum('amount')
                    : 0,
            ],
            'sales_by_stage' => $salesByStage,
            'recent_activities' => $recentActivities,
        ]);
    }
}
