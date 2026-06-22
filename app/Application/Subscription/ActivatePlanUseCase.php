<?php

namespace App\Application\Subscription;

use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Infrastructure\Persistence\Eloquent\Models\Plan;
use App\Infrastructure\Persistence\Eloquent\Models\PlanModule;
use App\Infrastructure\Persistence\Eloquent\Models\Subscription;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\Subscription\MockPaymentGateway;
use Illuminate\Support\Facades\DB;

class ActivatePlanUseCase
{
    public function __construct(
        protected MockPaymentGateway $paymentGateway,
    ) {}

    public function execute(Tenant $tenant, int $planId, array $moduleIds = []): Subscription
    {
        $plan = Plan::findOrFail($planId);

        $this->paymentGateway->charge((float) $plan->price, [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ]);

        return DB::transaction(function () use ($tenant, $plan, $moduleIds) {
            $subscription = Subscription::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active->value,
                    'starts_at' => now(),
                    'ends_at' => now()->addMonths($plan->duration_months),
                ]
            );

            $subscription->modules()->sync($moduleIds);

            $tenant->update(['trial_ends_at' => null]);

            return $subscription->load(['plan', 'modules']);
        });
    }

    public function addModules(Subscription $subscription, array $moduleIds): Subscription
    {
        $modules = PlanModule::whereIn('id', $moduleIds)->get();
        $total = $modules->sum('price');

        $this->paymentGateway->charge((float) $total, [
            'subscription_id' => $subscription->id,
            'module_ids' => $moduleIds,
        ]);

        $subscription->modules()->syncWithoutDetaching($moduleIds);

        return $subscription->load('modules');
    }
}
