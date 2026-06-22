<?php

namespace App\Application\DailyWork;

use App\Infrastructure\Persistence\Eloquent\Models\DailyWorkEntry;
use App\Infrastructure\Persistence\Eloquent\Models\DailyWorkReport;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\PermissionResolverService;
use App\Infrastructure\Services\TenantContext;
use App\Models\User;
use App\Notifications\CrmReminderNotification;
use App\Support\DepartmentAccessService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class DailyWorkReportService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected PermissionResolverService $permissions,
        protected DepartmentAccessService $departments,
    ) {}

    public function isManager(User $user): bool
    {
        return $this->permissions->isManagerRole($user, $this->tenant()->id);
    }

    /** Can the user see reports of their department members? */
    public function canViewTeam(User $user): bool
    {
        if ($this->isOwner($user)) {
            return true;
        }

        $tenantId = $this->tenant()->id;

        return $this->permissions->hasPermission($user, $tenantId, 'daily_reports.view_team')
            || $this->permissions->isManagerRole($user, $tenantId);
    }

    public function list(User $user, Request $request): LengthAwarePaginator
    {
        $query = DailyWorkReport::with(['user', 'entries', 'reviewer'])->orderByDesc('report_date');

        $this->applyVisibilityScope($query, $user, $this->tenant()->id);

        if ($request->filled('user_id') && $this->canViewTeam($user)) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from')) {
            $query->where('report_date', '>=', Carbon::parse($request->input('from'))->toDateString());
        }

        if ($request->filled('to')) {
            $query->where('report_date', '<=', Carbon::parse($request->input('to'))->toDateString());
        }

        if ($request->boolean('mine')) {
            $query->where('user_id', $user->id);
        }

        if ($request->input('review_status') === 'pending') {
            $query->where('status', 'submitted')->whereNull('manager_score');
        } elseif ($request->input('review_status') === 'reviewed') {
            $query->whereNotNull('manager_score');
        }

        return $query->paginate((int) $request->input('per_page', 15));
    }

    public function show(User $user, DailyWorkReport $report): DailyWorkReport
    {
        if (! $this->canView($user, $report)) {
            abort(403);
        }

        return $report->load(['user', 'entries', 'reviewer']);
    }

    public function review(User $user, DailyWorkReport $report, array $data): DailyWorkReport
    {
        if (! $this->canReview($user, $report)) {
            abort(403, 'شما مجاز به بازبینی این گزارش نیستید.');
        }

        if ($report->status !== 'submitted') {
            abort(422, 'فقط گزارش‌های ارسال‌شده قابل بازبینی هستند.');
        }

        $report->update([
            'manager_score' => min(5, max(1, (int) $data['manager_score'])),
            'manager_feedback' => $data['manager_feedback'] ?? null,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return $report->fresh(['user', 'entries', 'reviewer']);
    }

    /** @return array<int, array<string, mixed>> */
    public function monthlyPerformance(User $user, ?string $month = null): array
    {
        if (! $this->canViewTeam($user)) {
            abort(403);
        }

        $monthDate = $month
            ? Carbon::parse($month.'-01')
            : now()->startOfMonth();

        $from = $monthDate->copy()->startOfMonth()->toDateString();
        $to = $monthDate->copy()->endOfMonth()->toDateString();
        $tenantId = $this->tenant()->id;

        $query = DailyWorkReport::query()
            ->where('status', 'submitted')
            ->whereBetween('report_date', [$from, $to])
            ->with('user');

        $this->applyVisibilityScope($query, $user, $tenantId);

        $reports = $query->get();
        $workingDays = max(1, $monthDate->daysInMonth - 4);

        return $reports
            ->groupBy('user_id')
            ->map(function ($userReports, $userId) use ($workingDays) {
                $reviewed = $userReports->whereNotNull('manager_score');
                $avgScore = $reviewed->isNotEmpty()
                    ? round($reviewed->avg('manager_score'), 1)
                    : null;

                return [
                    'user_id' => (int) $userId,
                    'name' => $userReports->first()->user?->name ?? 'کاربر',
                    'reports_submitted' => $userReports->count(),
                    'reports_reviewed' => $reviewed->count(),
                    'pending_review' => $userReports->whereNull('manager_score')->count(),
                    'avg_manager_score' => $avgScore,
                    'total_work_minutes' => (int) $userReports->sum('total_minutes'),
                    'submission_rate' => round(min(100, ($userReports->count() / $workingDays) * 100), 1),
                    'quality_label' => $this->qualityLabel($avgScore),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['avg_manager_score'] ?? 0)
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            })
            ->all();
    }

    protected function qualityLabel(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 4.5 => 'عالی',
            $score >= 3.5 => 'خوب',
            $score >= 2.5 => 'متوسط',
            default => 'نیاز به بهبود',
        };
    }

    public function create(User $user, array $data): DailyWorkReport
    {
        $reportDate = Carbon::parse($data['report_date'])->toDateString();

        $existing = DailyWorkReport::query()
            ->where('user_id', $user->id)
            ->where('report_date', $reportDate)
            ->first();

        if ($existing) {
            abort(422, 'گزارش این روز قبلاً ثبت شده است.');
        }

        $report = DailyWorkReport::create([
            'user_id' => $user->id,
            'report_date' => $reportDate,
            'status' => 'draft',
            'summary' => $data['summary'] ?? null,
        ]);

        $this->syncEntries($report, $data['entries'] ?? []);

        return $report->fresh(['user', 'entries']);
    }

    public function canEdit(User $user, DailyWorkReport $report): bool
    {
        if ((int) $report->user_id !== (int) $user->id) {
            return false;
        }

        if ($report->status === 'draft') {
            return true;
        }

        if ($report->status !== 'submitted' || $report->manager_score !== null) {
            return false;
        }

        if (! $report->submitted_at) {
            return false;
        }

        return $report->submitted_at->greaterThan(now()->subHours(24));
    }

    public function update(User $user, DailyWorkReport $report, array $data): DailyWorkReport
    {
        if (! $this->canEdit($user, $report)) {
            abort(422, 'این گزارش دیگر قابل ویرایش نیست. ویرایش تا ۲۴ ساعت پس از ارسال و قبل از ثبت بازخورد مدیر مجاز است.');
        }

        if (array_key_exists('summary', $data)) {
            $report->summary = $data['summary'];
        }

        if (array_key_exists('entries', $data)) {
            $this->syncEntries($report, $data['entries']);
        }

        $report->save();

        return $report->fresh(['user', 'entries']);
    }

    public function submit(User $user, DailyWorkReport $report): DailyWorkReport
    {
        if ((int) $report->user_id !== (int) $user->id) {
            abort(403, 'فقط صاحب گزارش می‌تواند آن را ارسال کند.');
        }

        if ($report->status === 'submitted') {
            abort(422, 'این گزارش قبلاً ارسال شده است.');
        }

        $report->load('entries');

        if ($report->entries->isEmpty()) {
            abort(422, 'حداقل یک آیتم کار برای ارسال گزارش لازم است.');
        }

        $report->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'total_minutes' => (int) $report->entries->sum('minutes'),
        ]);

        $this->notifyManagers($user, $report->fresh(['user', 'entries']));

        return $report;
    }

    public function delete(User $user, DailyWorkReport $report): void
    {
        if (! $this->canModify($user, $report)) {
            abort(403);
        }

        if ($report->status === 'submitted' && ! $this->isOwner($user)) {
            abort(403, 'گزارش ارسال‌شده فقط توسط مالک قابل حذف است.');
        }

        $report->delete();
    }

    public function getOrCreateToday(User $user): DailyWorkReport
    {
        $today = now()->toDateString();

        $report = DailyWorkReport::query()
            ->where('user_id', $user->id)
            ->where('report_date', $today)
            ->with(['user', 'entries'])
            ->first();

        if ($report) {
            return $report;
        }

        return DailyWorkReport::create([
            'user_id' => $user->id,
            'report_date' => $today,
            'status' => 'draft',
        ])->load(['user', 'entries']);
    }

    protected function syncEntries(DailyWorkReport $report, array $entries): void
    {
        $report->entries()->delete();

        $totalMinutes = 0;

        foreach (array_values($entries) as $index => $entry) {
            if (empty($entry['title'])) {
                continue;
            }

            $minutes = max(0, (int) ($entry['minutes'] ?? 0));
            $totalMinutes += $minutes;

            DailyWorkEntry::create([
                'daily_work_report_id' => $report->id,
                'title' => $entry['title'],
                'description' => $entry['description'] ?? null,
                'minutes' => $minutes,
                'effort_score' => min(5, max(1, (int) ($entry['effort_score'] ?? 3))),
                'task_id' => $entry['task_id'] ?? null,
                'sort_order' => $index,
            ]);
        }

        $report->total_minutes = $totalMinutes;
        $report->save();
    }

    protected function applyVisibilityScope($query, User $user, int $tenantId): void
    {
        if ($this->isOwner($user)) {
            return;
        }

        if ($this->canViewTeam($user)) {
            $department = $this->permissions->departmentFor($user, $tenantId);

            if ($department) {
                $memberIds = $this->departments->departmentMemberIds($tenantId, $department);
                $query->where(function ($q) use ($user, $memberIds) {
                    $q->where('user_id', $user->id)->orWhereIn('user_id', $memberIds);
                });

                return;
            }

            if ($this->isOwner($user)) {
                return;
            }
        }

        $query->where('user_id', $user->id);
    }

    protected function canView(User $user, DailyWorkReport $report): bool
    {
        if ((int) $report->user_id === (int) $user->id) {
            return true;
        }

        if ($this->isOwner($user)) {
            return true;
        }

        $tenantId = $this->tenant()->id;

        if ($this->canViewTeam($user)) {
            $department = $this->permissions->departmentFor($user, $tenantId);

            if ($department) {
                return in_array((int) $report->user_id, $this->departments->departmentMemberIds($tenantId, $department), true);
            }

            return $this->isOwner($user);
        }

        return false;
    }

    protected function canModify(User $user, DailyWorkReport $report): bool
    {
        return (int) $report->user_id === (int) $user->id || $this->isOwner($user);
    }

    protected function canReview(User $user, DailyWorkReport $report): bool
    {
        if ($this->isOwner($user)) {
            return true;
        }

        if (! $this->permissions->hasPermission($user, $this->tenant()->id, 'daily_reports.review')) {
            return false;
        }

        return $this->canView($user, $report);
    }

    protected function isOwner(User $user): bool
    {
        return $this->permissions->isOwnerRole($user, $this->tenant()->id);
    }

    protected function notifyManagers(User $submitter, DailyWorkReport $report): void
    {
        $tenantId = $this->tenant()->id;
        $department = $this->permissions->departmentFor($submitter, $tenantId);

        if (! $department) {
            return;
        }

        $memberIds = $this->departments->departmentMemberIds($tenantId, $department);

        setPermissionsTeamId($tenantId);

        $managers = User::query()
            ->whereIn('id', $memberIds)
            ->get()
            ->filter(fn (User $member) => $this->permissions->hasPermission($member, $tenantId, 'daily_reports.review'));

        $dateLabel = $report->report_date->format('Y-m-d');

        foreach ($managers as $manager) {
            if ((int) $manager->id === (int) $submitter->id) {
                continue;
            }

            $manager->notify(new CrmReminderNotification(
                title: 'گزارش کار روزانه',
                subtitle: "{$submitter->name} — {$dateLabel}",
                url: '/apps/crm/daily-reports',
                entityType: 'daily_work_report',
                entityId: $report->id,
                tenantId: $tenantId,
                color: 'info',
                icon: 'tabler-report',
            ));
        }
    }

    protected function tenant(): Tenant
    {
        return Tenant::findOrFail($this->tenantContext->tenantId());
    }
}
