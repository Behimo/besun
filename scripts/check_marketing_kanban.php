<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenant = App\Infrastructure\Persistence\Eloquent\Models\Tenant::first();
$owner = App\Models\User::find($tenant->owner_id);
$access = app(App\Support\DepartmentAccessService::class);

echo 'Total leads: '.App\Infrastructure\Persistence\Eloquent\Models\Lead::count()."\n";
echo 'Marketing stages: '.App\Infrastructure\Persistence\Eloquent\Models\PipelineStage::where('type', 'marketing')->count()."\n";

try {
    $stages = App\Infrastructure\Persistence\Eloquent\Models\PipelineStage::query()
        ->where('type', 'marketing')
        ->with(['leads' => function ($q) use ($owner, $tenant, $access) {
            $q->with(['campaign', 'assignee:id,name', 'contact:id,name'])
                ->where('status', '!=', 'converted')
                ->orderByDesc('updated_at');
            $access->scopeDepartmentRecords($q, $owner, $tenant->id);
        }])
        ->withCount(['leads as leads_count' => function ($q) use ($owner, $tenant, $access) {
            $q->where('status', '!=', 'converted');
            $access->scopeDepartmentRecords($q, $owner, $tenant->id);
        }])
        ->orderBy('sort_order')
        ->get();

    $leadCount = $stages->sum(fn ($s) => $s->leads->count());
    echo "Owner marketing kanban: {$stages->count()} stages, {$leadCount} leads\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}
