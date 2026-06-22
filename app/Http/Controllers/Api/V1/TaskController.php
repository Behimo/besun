<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Task\TaskService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected TaskService $tasks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('tasks.read');

        $paginator = $this->tasks->list($request->user(), $request);

        return TaskResource::collection($paginator)->response();
    }

    public function assignees(Request $request): JsonResponse
    {
        return response()->json([
            'users' => $this->tasks->assignableUsers($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('tasks.create');

        $request->replace($this->normalizeTaskInput($request->all()));
        $data = $request->validate($this->rules());

        $task = $this->tasks->create($request->user(), $data);

        return response()->json([
            'task' => new TaskResource($task->load(['assignee', 'creator', 'assigner'])),
        ], 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->requirePermission('tasks.read');

        if (! $this->tasks->canView($request->user(), $task)) {
            abort(403);
        }

        return response()->json([
            'task' => new TaskResource($task->load(['assignee', 'creator', 'assigner'])),
        ]);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->requirePermission('tasks.update');

        $request->replace($this->normalizeTaskInput($request->all()));
        $data = $request->validate($this->rules(isUpdate: true));

        if (($data['status'] ?? $task->status) === 'completed'
            && empty($data['work_started_at'])
            && empty($data['work_ended_at'])
            && empty($task->work_started_at)
            && empty($task->work_ended_at)) {
            throw ValidationException::withMessages([
                'work_started_at' => ['ساعت شروع کار را وارد کنید.'],
                'work_ended_at' => ['ساعت پایان کار را وارد کنید.'],
            ]);
        }

        $task = $this->tasks->update($request->user(), $task, $data);

        return response()->json([
            'task' => new TaskResource($task),
        ]);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->requirePermission('tasks.delete');

        $this->tasks->delete($request->user(), $task);

        return response()->json(['message' => 'Deleted.']);
    }

    protected function rules(bool $isUpdate = false): array
    {
        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'completion_note' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:pending,in_progress,completed'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'effort_points' => ['nullable', 'integer', 'min:1', 'max:5'],
            'due_at' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'related_type' => ['nullable', 'string'],
            'related_id' => ['nullable', 'integer'],
            'work_started_at' => ['nullable', 'date'],
            'work_ended_at' => ['nullable', 'date'],
            'time_spent_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function normalizeTaskInput(array $data): array
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        foreach (['due_at', 'reminder_at', 'work_started_at', 'work_ended_at'] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = null;
                }

                continue;
            }

            $value = str_replace($persian, $latin, (string) $data[$field]);

            if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}):\d{2}:\d{2}$/', $value, $matches)) {
                $value = $matches[1].':00';
            } elseif (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})$/', $value, $matches)) {
                $value = $matches[1].':00';
            }

            try {
                $data[$field] = Carbon::parse($value)->format('Y-m-d\TH:i:s');
            } catch (\Throwable) {
                $data[$field] = $value;
            }
        }

        return $data;
    }
}
