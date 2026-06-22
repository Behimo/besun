<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Platform\PlatformAuditService;
use App\Application\Platform\PlatformStaffManagementService;
use App\Http\Controllers\Controller;
use App\Models\PlatformStaff;
use App\Support\PlatformAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformStaffController extends Controller
{
    public function __construct(
        protected PlatformStaffManagementService $staff,
        protected PlatformAuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->staff->list($request->query());

        return response()->json([
            'staff' => collect($paginator->items())->map(fn ($s) => $this->staff->format($s)),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var PlatformStaff $actor */
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'role' => ['required', 'string', 'in:admin,support'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $created = $this->staff->create($actor, $data);

        $this->audit->log($actor, 'staff.created', 'platform_staff', $created->id, [
            'email' => $created->email,
            'role' => $created->roleEnum()->value,
        ]);

        return response()->json([
            'message' => 'کاربر پلتفرم ایجاد شد.',
            'staff' => $this->staff->format($created),
        ], 201);
    }

    public function update(Request $request, PlatformStaff $staffMember): JsonResponse
    {
        /** @var PlatformStaff $actor */
        $actor = $request->user();

        if ($staffMember->isSuperAdmin()) {
            abort(403, 'مدیر کل قابل ویرایش نیست.');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'role' => ['sometimes', 'string', 'in:admin,support'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->staff->update($staffMember, $data);

        $this->audit->log($actor, 'staff.updated', 'platform_staff', $updated->id);

        return response()->json([
            'message' => 'کاربر به‌روز شد.',
            'staff' => $this->staff->format($updated),
        ]);
    }
}
