<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('tenants')
            ->leftJoin('tenant_sms_accounts', 'tenant_sms_accounts.tenant_id', '=', 'tenants.id')
            ->whereNull('tenant_sms_accounts.id')
            ->select('tenants.id')
            ->orderBy('tenants.id')
            ->chunkById(200, function ($tenants) use ($now) {
                $rows = $tenants->map(fn ($tenant) => [
                    'tenant_id' => $tenant->id,
                    'status' => 'draft',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows) {
                    DB::table('tenant_sms_accounts')->insert($rows);
                }
            }, 'tenants.id', 'id');
    }

    public function down(): void
    {
        DB::table('tenant_sms_accounts')
            ->where('status', 'draft')
            ->whereNull('ippanel_user_id')
            ->whereNull('ippanel_username')
            ->whereNull('api_key_encrypted')
            ->whereNull('password_encrypted')
            ->whereNull('default_from_number')
            ->delete();
    }
};
