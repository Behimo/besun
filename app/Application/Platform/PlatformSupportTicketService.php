<?php

namespace App\Application\Platform;

use App\Infrastructure\Persistence\Eloquent\Models\PlatformSupportTicket;
use App\Infrastructure\Persistence\Eloquent\Models\PlatformSupportTicketMessage;
use App\Models\PlatformStaff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformSupportTicketService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 50);
        $page = max((int) ($filters['page'] ?? 1), 1);

        $query = PlatformSupportTicket::query()
            ->with(['tenant:id,name,slug', 'creator:id,name', 'assignee:id,name'])
            ->withCount('messages')
            ->orderByDesc('updated_at');

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($priority = $filters['priority'] ?? null) {
            $query->where('priority', $priority);
        }

        if (($filters['open_only'] ?? null) === '1') {
            $query->whereIn('status', [
                PlatformSupportTicket::STATUS_OPEN,
                PlatformSupportTicket::STATUS_IN_PROGRESS,
            ]);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function create(PlatformStaff $staff, array $data): PlatformSupportTicket
    {
        $ticket = PlatformSupportTicket::create([
            'tenant_id' => $data['tenant_id'] ?? null,
            'creator_staff_id' => $staff->id,
            'assignee_staff_id' => $data['assignee_staff_id'] ?? null,
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => PlatformSupportTicket::STATUS_OPEN,
        ]);

        if (! empty($data['description'])) {
            PlatformSupportTicketMessage::create([
                'ticket_id' => $ticket->id,
                'platform_staff_id' => $staff->id,
                'body' => $data['description'],
                'is_internal' => false,
            ]);
        }

        return $ticket->load(['tenant:id,name', 'creator:id,name', 'assignee:id,name']);
    }

    public function show(PlatformSupportTicket $ticket): array
    {
        $ticket->load([
            'tenant:id,name,slug',
            'creator:id,name,email',
            'assignee:id,name',
            'messages.platformStaff:id,name',
        ]);

        return [
            'ticket' => $this->formatTicket($ticket),
            'messages' => $ticket->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'is_internal' => $m->is_internal,
                'user' => $m->platformStaff ? ['id' => $m->platformStaff->id, 'name' => $m->platformStaff->name] : null,
                'created_at' => $m->created_at,
            ]),
        ];
    }

    public function update(PlatformSupportTicket $ticket, array $data): PlatformSupportTicket
    {
        $updates = [];

        foreach (['status', 'priority', 'assignee_staff_id', 'subject'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('assigned_to', $data)) {
            $updates['assignee_staff_id'] = $data['assigned_to'];
        }

        if (($updates['status'] ?? null) === PlatformSupportTicket::STATUS_RESOLVED) {
            $updates['resolved_at'] = now();
        }

        $ticket->update($updates);

        return $ticket->fresh(['tenant:id,name', 'creator:id,name', 'assignee:id,name']);
    }

    public function addMessage(PlatformStaff $staff, PlatformSupportTicket $ticket, string $body, bool $isInternal = false): PlatformSupportTicketMessage
    {
        $message = PlatformSupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'platform_staff_id' => $staff->id,
            'body' => $body,
            'is_internal' => $isInternal,
        ]);

        if ($ticket->status === PlatformSupportTicket::STATUS_OPEN) {
            $ticket->update(['status' => PlatformSupportTicket::STATUS_IN_PROGRESS]);
        }

        $ticket->touch();

        return $message->load('platformStaff:id,name');
    }

    public function openCount(): int
    {
        return PlatformSupportTicket::whereIn('status', [
            PlatformSupportTicket::STATUS_OPEN,
            PlatformSupportTicket::STATUS_IN_PROGRESS,
        ])->count();
    }

    public function formatTicket(PlatformSupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'tenant_id' => $ticket->tenant_id,
            'tenant_name' => $ticket->tenant?->name,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'created_by' => $ticket->creator ? ['id' => $ticket->creator->id, 'name' => $ticket->creator->name] : null,
            'assigned_to' => $ticket->assignee ? ['id' => $ticket->assignee->id, 'name' => $ticket->assignee->name] : null,
            'messages_count' => $ticket->messages_count ?? $ticket->messages()->count(),
            'resolved_at' => $ticket->resolved_at,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ];
    }
}
