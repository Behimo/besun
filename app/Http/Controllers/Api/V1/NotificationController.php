<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Notification\BroadcastNotificationService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\BroadcastMessage;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected BroadcastNotificationService $broadcasts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($n) => $this->formatNotification($n));

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function broadcast(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($this->tenantContext->tenantId());

        if (! $tenant->isManagerOrOwner($request->user())) {
            abort(403, 'فقط مدیر یا مالک مجموعه می‌تواند پیام ارسال کند.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'url' => ['nullable', 'string', 'max:500'],
            'kind' => ['nullable', 'in:broadcast,system'],
        ]);

        $message = $this->broadcasts->sendToTenant(
            $tenant,
            $request->user(),
            $data['title'],
            $data['body'],
            $data['url'] ?? null,
            $data['kind'] ?? 'broadcast',
        );

        return response()->json([
            'message' => 'پیام برای '.$message->recipients_count.' کاربر ارسال شد.',
            'broadcast' => $message,
        ], 201);
    }

    public function broadcastHistory(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($this->tenantContext->tenantId());

        if (! $tenant->isManagerOrOwner($request->user())) {
            abort(403);
        }

        $items = BroadcastMessage::with('sender:id,name')
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json(['broadcasts' => $items]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['message' => 'OK']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'OK']);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    protected function formatNotification($n): array
    {
        $entityType = $n->data['entity_type'] ?? 'reminder';

        return [
            'id' => $n->id,
            'title' => $n->data['title'] ?? '',
            'subtitle' => $n->data['subtitle'] ?? '',
            'url' => $n->data['url'] ?? null,
            'entity_type' => $entityType,
            'entity_id' => $n->data['entity_id'] ?? null,
            'color' => $n->data['color'] ?? 'primary',
            'icon' => $n->data['icon'] ?? $this->defaultIcon($entityType),
            'time' => $n->created_at?->diffForHumans(),
            'isSeen' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }

    protected function defaultIcon(string $entityType): string
    {
        return match ($entityType) {
            'task', 'task_reminder' => 'tabler-checkbox',
            'lead_follow_up' => 'tabler-user-search',
            'deal_follow_up' => 'tabler-chart-funnel',
            'activity' => 'tabler-calendar-event',
            'broadcast' => 'tabler-speakerphone',
            'system' => 'tabler-info-circle',
            default => 'tabler-bell',
        };
    }
}
