<?php

namespace App\Application\Platform;

use App\Infrastructure\Persistence\Eloquent\Models\PlatformAuditLog;
use App\Models\PlatformStaff;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class PlatformAuditService
{
    public function log(
        Authenticatable $actor,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $meta = null,
    ): PlatformAuditLog {
        $data = [
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta,
        ];

        if ($actor instanceof PlatformStaff) {
            $data['platform_staff_id'] = $actor->id;
        } elseif ($actor instanceof User) {
            $data['user_id'] = $actor->id;
        }

        return PlatformAuditLog::create($data);
    }

    public function list(array $filters, int $perPage)
    {
        return PlatformAuditLog::query()
            ->with(['user:id,name,phone', 'platformStaff:id,name,email'])
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (PlatformAuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'meta' => $log->meta,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                ] : null,
                'platform_staff' => $log->platformStaff ? [
                    'id' => $log->platformStaff->id,
                    'name' => $log->platformStaff->name,
                    'email' => $log->platformStaff->email,
                ] : null,
                'created_at' => $log->created_at,
            ]);
    }
}
