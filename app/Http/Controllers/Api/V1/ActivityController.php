<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Reminder\ReminderProcessor;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Support\NormalizesCrmRelatedType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    use ChecksCrmAccess;
    use NormalizesCrmRelatedType;

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('activities.read');

        $query = Activity::with('user');

        if ($request->boolean('upcoming')) {
            $query->whereNull('happened_at')
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '>', now())
                ->orderBy('scheduled_at');
        } else {
            $query->latest('happened_at');
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $activities = $query->paginate(20);

        return response()->json($activities);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('activities.create');

        $data = $request->validate([
            'type' => ['required', 'in:call,meeting,note,email,sms'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'happened_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'related_type' => ['nullable', 'string'],
            'related_id' => ['nullable', 'integer'],
        ]);

        $scheduledAt = ! empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null;
        $isFutureSchedule = $scheduledAt && $scheduledAt->isFuture();

        if ($isFutureSchedule) {
            $data['happened_at'] = null;
            $data['reminder_at'] = ReminderProcessor::computeActivityReminder(
                $data['scheduled_at'],
                $data['reminder_at'] ?? null,
            )?->toDateTimeString();
        } else {
            $data['happened_at'] = $data['happened_at'] ?? ($scheduledAt ?? now());
            $data['scheduled_at'] = null;
            $data['reminder_at'] = null;
        }

        $activity = Activity::create([
            ...$data,
            'related_type' => $this->normalizeRelatedType($data['related_type'] ?? null),
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['activity' => $activity->load('user')], 201);
    }

    public function update(Request $request, Activity $activity): JsonResponse
    {
        $this->requirePermission('activities.update');

        if ((int) $activity->user_id !== (int) $request->user()->id && ! $this->isTenantOwner()) {
            abort(403);
        }

        $data = $request->validate([
            'type' => ['sometimes', 'in:call,meeting,note,email,sms'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'happened_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $activity->update($data);

        return response()->json(['activity' => $activity->fresh('user')]);
    }

    public function destroy(Activity $activity): JsonResponse
    {
        $this->requirePermission('activities.delete');

        if ((int) $activity->user_id !== (int) request()->user()->id && ! $this->isTenantOwner()) {
            abort(403, 'فقط ثبت‌کننده فعالیت یا مالک می‌تواند آن را حذف کند.');
        }

        $activity->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    protected function isTenantOwner(): bool
    {
        return Tenant::findOrFail($this->crmTenantId())->isOwner(request()->user());
    }
}
