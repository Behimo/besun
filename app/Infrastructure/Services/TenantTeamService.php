<?php

namespace App\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\TenantTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantTeamService
{
    /** @var array<int, array{slug: string, name: string, sort_order: int}> */
    public const SYSTEM_TEAMS = [
        ['slug' => 'sales', 'name' => 'فروش', 'sort_order' => 1],
        ['slug' => 'marketing', 'name' => 'بازاریابی', 'sort_order' => 2],
        ['slug' => 'finance', 'name' => 'مالی', 'sort_order' => 3],
    ];

    public function ensureSystemTeams(int $tenantId): void
    {
        $now = now();

        foreach (self::SYSTEM_TEAMS as $team) {
            $exists = DB::table('tenant_teams')
                ->where('tenant_id', $tenantId)
                ->where('slug', $team['slug'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tenant_teams')->insert([
                'tenant_id' => $tenantId,
                'name' => $team['name'],
                'slug' => $team['slug'],
                'is_system' => true,
                'sort_order' => $team['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @return Collection<int, TenantTeam> */
    public function list(int $tenantId): Collection
    {
        $this->ensureSystemTeams($tenantId);

        return TenantTeam::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, array{value: string, label: string, is_system: bool}> */
    public function options(int $tenantId): array
    {
        return $this->list($tenantId)->map(fn (TenantTeam $team) => [
            'value' => $team->slug,
            'label' => $team->name,
            'is_system' => (bool) $team->is_system,
        ])->values()->all();
    }

    /** @return array<int, string> */
    public function slugs(int $tenantId): array
    {
        return $this->list($tenantId)->pluck('slug')->all();
    }

    public function label(int $tenantId, ?string $slug): ?string
    {
        if (! $slug) {
            return null;
        }

        return TenantTeam::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->value('name') ?? $slug;
    }

    public function exists(int $tenantId, string $slug): bool
    {
        return in_array($slug, $this->slugs($tenantId), true);
    }

    public function teamRule(int $tenantId): \Illuminate\Validation\Rules\In
    {
        return Rule::in($this->slugs($tenantId));
    }

    public function format(TenantTeam $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'is_system' => (bool) $team->is_system,
            'members_count' => $this->membersCount($team),
            'roles_count' => $this->rolesCount($team),
        ];
    }

    public function create(int $tenantId, string $name): TenantTeam
    {
        $this->ensureSystemTeams($tenantId);

        $slug = $this->uniqueSlug($tenantId, $name);
        $sortOrder = (int) TenantTeam::query()->where('tenant_id', $tenantId)->max('sort_order') + 1;

        return TenantTeam::create([
            'tenant_id' => $tenantId,
            'name' => trim($name),
            'slug' => $slug,
            'is_system' => false,
            'sort_order' => $sortOrder,
        ]);
    }

    public function update(TenantTeam $team, string $name): TenantTeam
    {
        $team->update(['name' => trim($name)]);

        return $team->fresh();
    }

    public function delete(TenantTeam $team): void
    {
        if ($team->is_system) {
            abort(422, 'تیم‌های پیش‌فرض قابل حذف نیستند');
        }

        if ($this->membersCount($team) > 0) {
            abort(422, 'ابتدا اعضای این تیم را به تیم دیگری منتقل کنید');
        }

        if ($this->rolesCount($team) > 0) {
            abort(422, 'ابتدا نقش‌های این تیم را حذف کنید');
        }

        $team->delete();
    }

    public function findForTenant(int $tenantId, int $teamId): TenantTeam
    {
        return TenantTeam::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($teamId)
            ->firstOrFail();
    }

    protected function membersCount(TenantTeam $team): int
    {
        return (int) DB::table('tenant_user')
            ->where('tenant_id', $team->tenant_id)
            ->whereNull('left_at')
            ->where('department', $team->slug)
            ->count();
    }

    protected function rolesCount(TenantTeam $team): int
    {
        return (int) DB::table('roles')
            ->where('tenant_id', $team->tenant_id)
            ->where('department', $team->slug)
            ->count();
    }

    protected function uniqueSlug(int $tenantId, string $name): string
    {
        $base = Str::slug($name, '_');

        if ($base === '' || ! preg_match('/[a-z]/', $base)) {
            $base = 'team';
        }

        $base = 'team_'.$base;
        $slug = $base;
        $suffix = 1;

        while (
            collect(self::SYSTEM_TEAMS)->pluck('slug')->contains($slug)
            || TenantTeam::query()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()
        ) {
            $slug = $base.'_'.(++$suffix);
        }

        return $slug;
    }
}
