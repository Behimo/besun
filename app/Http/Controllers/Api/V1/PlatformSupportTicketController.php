<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Platform\PlatformAuditService;
use App\Application\Platform\PlatformSupportTicketService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\PlatformSupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSupportTicketController extends Controller
{
    public function __construct(
        protected PlatformSupportTicketService $tickets,
        protected PlatformAuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->tickets->list($request->query());

        return response()->json([
            'tickets' => collect($paginator->items())->map(
                fn ($t) => $this->tickets->formatTicket($t),
            ),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket = $this->tickets->create($request->user(), $data);

        $this->audit->log($request->user(), 'ticket.created', 'ticket', $ticket->id, [
            'subject' => $ticket->subject,
        ]);

        return response()->json([
            'message' => 'تیکت ثبت شد.',
            'ticket' => $this->tickets->formatTicket($ticket),
        ], 201);
    }

    public function show(PlatformSupportTicket $ticket): JsonResponse
    {
        return response()->json($this->tickets->show($ticket));
    }

    public function update(Request $request, PlatformSupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:open,in_progress,resolved,closed'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'subject' => ['sometimes', 'string', 'max:255'],
        ]);

        $before = $ticket->status;
        $updated = $this->tickets->update($ticket, $data);

        $this->audit->log($request->user(), 'ticket.updated', 'ticket', $ticket->id, [
            'before_status' => $before,
            'after_status' => $updated->status,
        ]);

        return response()->json([
            'message' => 'تیکت به‌روز شد.',
            'ticket' => $this->tickets->formatTicket($updated),
        ]);
    }

    public function addMessage(Request $request, PlatformSupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $message = $this->tickets->addMessage(
            $request->user(),
            $ticket,
            $data['body'],
            $data['is_internal'] ?? false,
        );

        return response()->json([
            'message' => 'پیام ثبت شد.',
            'item' => [
                'id' => $message->id,
                'body' => $message->body,
                'is_internal' => $message->is_internal,
                'user' => $message->user ? ['id' => $message->user->id, 'name' => $message->user->name] : null,
                'created_at' => $message->created_at,
            ],
        ], 201);
    }
}
