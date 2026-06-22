<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Invitation\InvitationService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Invitation;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitations,
        protected TenantContext $tenantContext,
        protected TenantTeamService $teams,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'invitations' => $this->invitations->listForUser($request->user()),
        ]);
    }

    public function tenantIndex(Request $request, Tenant $tenant): JsonResponse
    {
        if (! $request->user()->belongsToTenant($tenant->id)) {
            abort(403);
        }

        if (! $tenant->isManagerOrOwner($request->user())) {
            abort(403, 'فقط مالک یا مدیر می‌تواند دعوت‌نامه‌ها را ببیند');
        }

        return response()->json([
            'invitations' => $this->invitations->listForTenant($tenant),
        ]);
    }

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        if (! $request->user()->belongsToTenant($tenant->id)) {
            abort(403);
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'role' => ['required', 'in:'.implode(',', app(\App\Infrastructure\Services\PermissionResolverService::class)->assignableRoleNames($tenant->id))],
            'department' => ['nullable', $this->teams->teamRule($tenant->id)],
        ]);

        try {
            $invitation = $this->invitations->createInvitation(
                $tenant,
                $data['phone'],
                $data['role'],
                $request->user(),
                $data['department'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['invitation' => $invitation->load(['tenant', 'inviter'])], 201);
    }

    public function accept(Request $request, Invitation $invitation): JsonResponse
    {
        try {
            $invitation = $this->invitations->accept($invitation, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['invitation' => $invitation]);
    }

    public function reject(Request $request, Invitation $invitation): JsonResponse
    {
        try {
            $invitation = $this->invitations->reject($invitation, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['invitation' => $invitation]);
    }

    public function destroy(Request $request, Invitation $invitation): JsonResponse
    {
        try {
            $invitation = $this->invitations->cancel($invitation, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['invitation' => $invitation]);
    }
}
