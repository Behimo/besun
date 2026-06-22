<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenant = App\Infrastructure\Persistence\Eloquent\Models\Tenant::first();
$owner = App\Models\User::find($tenant->owner_id);
$perms = app(App\Infrastructure\Services\PermissionResolverService::class);
$abilities = app(App\Infrastructure\Services\AbilityService::class);

echo 'User ID: '.$owner->id."\n";
echo 'Tenant owner_id: '.$tenant->owner_id."\n";
echo 'tenant.isOwner: '.($tenant->isOwner($owner) ? 'yes' : 'no')."\n";
echo 'Role: '.($perms->resolveRoleName($owner, $tenant->id) ?? 'none')."\n";
echo 'isOwnerRole: '.($perms->isOwnerRole($owner, $tenant->id) ? 'yes' : 'no')."\n";
echo 'Ability rules: '.json_encode($abilities->rulesFor($owner, $tenant->id), JSON_UNESCAPED_UNICODE)."\n";
echo 'Has marketing_funnel.read: '.($perms->hasPermission($owner, $tenant->id, 'marketing_funnel.read') ? 'yes' : 'no')."\n";
