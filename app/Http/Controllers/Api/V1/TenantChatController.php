<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\TenantChatGroup;
use App\Infrastructure\Persistence\Eloquent\Models\TenantChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantChatController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = app(\App\Infrastructure\Services\TenantContext::class)->tenantId();

        $groups = TenantChatGroup::query()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->withCount('members')
            ->with(['creator:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => [
                'type' => 'group',
                'id' => $g->id,
                'title' => $g->name,
                'members_count' => $g->members_count,
                'is_creator' => $g->created_by === $user->id,
            ]);

        $members = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->where('users.id', '!=', $user->id)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => [
                'type' => 'dm',
                'id' => $m->id,
                'title' => $m->name,
                'email' => $m->email,
            ]);

        return response()->json([
            'conversations' => [
                [
                    'type' => 'team',
                    'id' => 'team',
                    'title' => 'گفتگوی عمومی تیم',
                ],
                ...$groups,
                ...$members,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $groupId = $request->integer('group_id') ?: null;
        $recipientId = $request->integer('recipient_id') ?: null;

        if ($groupId) {
            $this->assertGroupMember($user->id, $groupId);
        }

        $messages = TenantChatMessage::query()
            ->with(['sender:id,name,email', 'recipient:id,name,email', 'group:id,name'])
            ->where(function ($q) use ($groupId, $recipientId, $user) {
                if ($groupId) {
                    $q->where('group_id', $groupId);
                } elseif ($recipientId) {
                    $q->where(function ($inner) use ($recipientId, $user) {
                        $inner->where('user_id', $user->id)
                            ->where('recipient_id', $recipientId);
                    })->orWhere(function ($inner) use ($recipientId, $user) {
                        $inner->where('user_id', $recipientId)
                            ->where('recipient_id', $user->id);
                    });
                } else {
                    $q->whereNull('recipient_id')->whereNull('group_id');
                }
            })
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'recipient_id' => ['nullable', 'exists:users,id'],
            'group_id' => ['nullable', 'exists:tenant_chat_groups,id'],
        ]);

        if (! empty($data['group_id']) && ! empty($data['recipient_id'])) {
            return response()->json(['message' => 'فقط یک نوع گفتگو را انتخاب کنید.'], 422);
        }

        if (! empty($data['group_id'])) {
            $this->assertGroupMember($user->id, (int) $data['group_id']);
        }

        $message = TenantChatMessage::create([
            'user_id' => $user->id,
            'recipient_id' => $data['recipient_id'] ?? null,
            'group_id' => $data['group_id'] ?? null,
            'body' => $data['body'],
        ]);

        return response()->json([
            'message' => $message->load(['sender:id,name,email', 'recipient:id,name,email', 'group:id,name']),
        ], 201);
    }

    public function members(Request $request): JsonResponse
    {
        $tenantId = app(\App\Infrastructure\Services\TenantContext::class)->tenantId();

        $users = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json(['members' => $users]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $memberIds = collect($data['member_ids'])
            ->push($user->id)
            ->unique()
            ->values()
            ->all();

        $group = TenantChatGroup::create([
            'name' => $data['name'],
            'created_by' => $user->id,
        ]);

        $group->members()->attach($memberIds, ['joined_at' => now()]);

        return response()->json([
            'group' => $group->load(['members:id,name,email'])->loadCount('members'),
        ], 201);
    }

    public function showGroup(Request $request, TenantChatGroup $group): JsonResponse
    {
        $this->assertGroupMember($request->user()->id, $group->id);

        return response()->json([
            'group' => $group->load(['members:id,name,email', 'creator:id,name'])->loadCount('members'),
        ]);
    }

    public function updateGroup(Request $request, TenantChatGroup $group): JsonResponse
    {
        if ($group->created_by !== $request->user()->id) {
            abort(403, 'فقط سازنده گروه می‌تواند آن را ویرایش کند.');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'member_ids' => ['sometimes', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        if (isset($data['name'])) {
            $group->update(['name' => $data['name']]);
        }

        if (isset($data['member_ids'])) {
            $memberIds = collect($data['member_ids'])
                ->push($request->user()->id)
                ->unique()
                ->values()
                ->all();

            $group->members()->sync(
                collect($memberIds)->mapWithKeys(fn ($id) => [$id => ['joined_at' => now()]])->all()
            );
        }

        return response()->json([
            'group' => $group->fresh()->load(['members:id,name,email'])->loadCount('members'),
        ]);
    }

    public function destroyGroup(Request $request, TenantChatGroup $group): JsonResponse
    {
        if ($group->created_by !== $request->user()->id) {
            abort(403, 'فقط سازنده گروه می‌تواند آن را حذف کند.');
        }

        $group->delete();

        return response()->json(['message' => 'گروه حذف شد.']);
    }

    protected function assertGroupMember(int $userId, int $groupId): void
    {
        $exists = TenantChatGroup::query()
            ->where('id', $groupId)
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->exists();

        if (! $exists) {
            abort(403, 'شما عضو این گروه نیستید.');
        }
    }
}
