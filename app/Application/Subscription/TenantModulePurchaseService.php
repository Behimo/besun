<?php

namespace App\Application\Subscription;

use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Infrastructure\Persistence\Eloquent\Models\Plan;
use App\Infrastructure\Persistence\Eloquent\Models\PlanModule;
use App\Infrastructure\Persistence\Eloquent\Models\Subscription;
use App\Infrastructure\Persistence\Eloquent\Models\SubscriptionTransaction;
use App\Application\Sms\TenantSmsProvisioningService;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\Subscription\MockPaymentGateway;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantModulePurchaseService
{
    private const PERIOD_DAYS = [
        'monthly' => 30,
        'semi_annual' => 180,
        'annual' => 365,
    ];

    private const MIN_SEATS = 1;

    private const MAX_SEATS = 100;

    public function __construct(
        protected MockPaymentGateway $paymentGateway,
        protected TenantSmsProvisioningService $smsProvisioning,
    ) {}

    /**
     * @param  array<int, array{module_id: int, period: string}>  $modulesInput
     */
    public function purchase(Tenant $tenant, array $modulesInput, ?int $seatCount = null, ?User $purchaser = null): Subscription
    {
        $normalized = $this->normalizeModulesInput($modulesInput);
        $seatCount = $this->resolveSeatCount($normalized, $seatCount);
        $moduleIds = collect($normalized)->pluck('module_id')->all();
        $modules = PlanModule::whereIn('id', $moduleIds)->get()->keyBy('id');

        $purchase = $this->buildPurchase($tenant, $normalized, $modules, $seatCount);

        $paymentResult = $this->paymentGateway->charge($purchase['total_amount'], [
            'tenant_id' => $tenant->id,
            'modules' => $purchase['payment_modules'],
            'seat_count' => $purchase['seat_count'],
        ]);

        return DB::transaction(function () use ($tenant, $purchase, $paymentResult, $purchaser) {
            $subscription = $this->ensureSubscription($tenant);

            foreach ($purchase['sync'] as $moduleId => $pivot) {
                $subscription->modules()->syncWithoutDetaching([
                    $moduleId => $pivot,
                ]);
            }

            if ($purchase['seat_count'] !== null) {
                $subscription->update(['seat_limit' => $purchase['seat_count']]);
            }

            if ($tenant->hasActiveCoreModule()) {
                $subscription->update([
                    'status' => SubscriptionStatus::Active->value,
                    'starts_at' => $subscription->starts_at ?? now(),
                    'ends_at' => $purchase['core_expires_at'],
                ]);
                $tenant->update(['trial_ends_at' => null]);
            }

            $this->provisionPurchasedModules($tenant, $purchase['payment_modules']);

            if ($purchaser) {
                SubscriptionTransaction::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $purchaser->id,
                    'amount' => $purchase['total_amount'],
                    'status' => 'paid',
                    'gateway_reference' => $paymentResult['transaction_id'] ?? null,
                    'items' => [
                        'modules' => $purchase['payment_modules'],
                        'seat_count' => $purchase['seat_count'],
                        'core_plan' => $purchase['core_plan'],
                    ],
                    'paid_at' => now(),
                ]);
            }

            return $subscription->load('modules');
        });
    }

    /**
     * @param  array<int, array{module_id: int, period: string}>  $modulesInput
     * @return array{
     *     sync: array<int, array<string, mixed>>,
     *     payment_modules: array<int, array<string, mixed>>,
     *     total_amount: float,
     *     core_plan: string,
     *     core_expires_at: Carbon,
     *     seat_count: ?int,
     *     seat_price_per_unit: ?float,
     *     seat_subtotal: ?float
     * }
     */
    public function buildPurchase(
        Tenant $tenant,
        array $modulesInput,
        SupportCollection $modules,
        ?int $seatCount = null,
    ): array {
        $seatCount = $this->resolveSeatCount($modulesInput, $seatCount, $modules);

        $subscription = $tenant->subscription;
        $existingCore = $subscription
            ? $subscription->modules()->where('plan_modules.is_core', true)->first()
            : null;

        $coreModuleInRequest = $modules->first(fn ($m) => $m->is_core);
        $corePlan = $this->resolveCorePlan($modulesInput, $modules, $existingCore);
        $coreIsActive = $this->coreIsActive($existingCore);

        if (! $existingCore && ! $coreModuleInRequest) {
            throw new RuntimeException('برای فعال‌سازی ماژول‌ها برای این مجموعه، ابتدا باید ماژول پایه را انتخاب کنید.');
        }

        if ($coreModuleInRequest && $seatCount === null) {
            throw new RuntimeException('تعداد کارمند (صندلی) برای ماژول پایه الزامی است.');
        }

        $hasNonCore = collect($modulesInput)->contains(function ($item) use ($modules) {
            $module = $modules->get($item['module_id'] ?? null);

            return $module && ! $module->is_core;
        });

        if ($hasNonCore && $existingCore && ! $coreIsActive) {
            throw new RuntimeException('اشتراک ماژول پایه این مجموعه منقضی شده است. ابتدا آن را تمدید کنید.');
        }

        if (! $existingCore || $hasNonCore) {
            $modulesInput = $this->alignAddonPeriodsWithCore($modulesInput, $modules, $corePlan);
        }

        foreach ($modulesInput as $item) {
            $module = $modules->get($item['module_id'] ?? null);
            if (! $module || $module->is_core) {
                continue;
            }

            if ($tenant->hasModule($module->slug)) {
                throw new RuntimeException("ماژول «{$module->name}» قبلاً برای این مجموعه فعال است.");
            }
        }

        $durationDays = self::PERIOD_DAYS[$corePlan] ?? 30;
        $coreExpiresAt = $this->resolveCoreExpiresAt($existingCore, $coreModuleInRequest !== null, $durationDays);
        $remainingDays = max(1, (int) ceil(now()->diffInSeconds($coreExpiresAt, false) / 86400));

        $totalAmount = 0.0;
        $paymentModules = [];
        $sync = [];
        $seatPricePerUnit = null;
        $seatSubtotal = null;

        foreach ($modulesInput as $item) {
            $module = $modules->get($item['module_id'] ?? null);
            if (! $module) {
                continue;
            }

            if ($module->is_core) {
                $seatPricePerUnit = $module->getSeatPriceForPeriod($corePlan);
                $seatSubtotal = $seatCount * $seatPricePerUnit;
                $pricePaid = $seatSubtotal;
                $expiresAt = $coreExpiresAt;
                $subscriptionType = $corePlan;
            } else {
                $monthly = (float) ($module->monthly_price ?? $module->price);
                $pricePaid = round(($monthly / 30) * $remainingDays, 2);
                $expiresAt = $coreExpiresAt;
                $subscriptionType = $corePlan;
            }

            $totalAmount += $pricePaid;
            $paymentModules[] = [
                'id' => $module->id,
                'title' => $module->name,
                'plan_type' => $subscriptionType,
                'price' => $pricePaid,
                'seat_count' => $module->is_core ? $seatCount : null,
            ];

            $sync[$module->id] = [
                'status' => 'active',
                'subscription_type' => $subscriptionType,
                'expires_at' => $expiresAt,
                'price_paid' => $pricePaid,
                'purchased_at' => now(),
            ];
        }

        if ($sync === []) {
            throw new RuntimeException('هیچ ماژول معتبری برای فعال‌سازی یافت نشد.');
        }

        return [
            'sync' => $sync,
            'payment_modules' => $paymentModules,
            'total_amount' => $totalAmount,
            'core_plan' => $corePlan,
            'core_expires_at' => $coreExpiresAt,
            'remaining_days' => $remainingDays,
            'seat_count' => $coreModuleInRequest ? $seatCount : null,
            'seat_price_per_unit' => $seatPricePerUnit,
            'seat_subtotal' => $seatSubtotal,
        ];
    }

    /**
     * @param  array<int, array{module_id: int, period: string}>  $modulesInput
     */
    public function normalizeModulesInput(array $modulesInput): array
    {
        $normalized = [];

        foreach ($modulesInput as $item) {
            if (! is_array($item)) {
                throw new RuntimeException('ساختار اطلاعات ماژول‌ها نامعتبر است.');
            }

            $moduleId = $item['module_id'] ?? $item['id'] ?? null;
            $period = $item['period'] ?? null;

            if (! $moduleId || ! $period) {
                throw new RuntimeException('ساختار اطلاعات ماژول‌ها نامعتبر است.');
            }

            if (! isset(self::PERIOD_DAYS[$period])) {
                throw new RuntimeException('نوع اشتراک انتخاب‌شده نامعتبر است.');
            }

            $normalized[] = [
                'module_id' => (int) $moduleId,
                'period' => $period,
            ];
        }

        if ($normalized === []) {
            throw new RuntimeException('هیچ ماژولی برای فعال‌سازی ارسال نشده است.');
        }

        return $normalized;
    }

    public function preview(Tenant $tenant, array $modulesInput, ?int $seatCount = null): array
    {
        $normalized = $this->normalizeModulesInput($modulesInput);
        $moduleIds = collect($normalized)->pluck('module_id')->all();
        $modules = PlanModule::whereIn('id', $moduleIds)->get()->keyBy('id');

        return $this->buildPurchase($tenant, $normalized, $modules, $seatCount);
    }

    /**
     * @param  array<int, array{module_id: int, period: string}>  $modulesInput
     */
    protected function resolveSeatCount(
        array $modulesInput,
        ?int $seatCount,
        ?SupportCollection $modules = null,
    ): ?int {
        $needsSeats = false;

        if ($modules) {
            foreach ($modulesInput as $item) {
                $module = $modules->get($item['module_id'] ?? null);
                if ($module?->is_core) {
                    $needsSeats = true;
                    break;
                }
            }
        } else {
            $moduleIds = collect($modulesInput)->pluck('module_id')->all();
            $needsSeats = PlanModule::whereIn('id', $moduleIds)->where('is_core', true)->exists();
        }

        if (! $needsSeats) {
            return null;
        }

        if ($seatCount === null) {
            return null;
        }

        if ($seatCount < self::MIN_SEATS || $seatCount > self::MAX_SEATS) {
            throw new RuntimeException('تعداد کارمند باید بین '.self::MIN_SEATS.' تا '.self::MAX_SEATS.' باشد.');
        }

        return $seatCount;
    }

    protected function ensureSubscription(Tenant $tenant): Subscription
    {
        if ($tenant->subscription) {
            return $tenant->subscription;
        }

        $plan = Plan::query()->where('is_active', true)->first()
            ?? Plan::query()->firstOrFail();

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'inactive',
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    private function resolveCorePlan(array $modulesInput, SupportCollection $modules, ?PlanModule $existingCore): string
    {
        foreach ($modulesInput as $item) {
            $module = $modules->get($item['module_id'] ?? null);
            if ($module && $module->is_core) {
                return $item['period'];
            }
        }

        if ($existingCore?->pivot?->subscription_type) {
            return $existingCore->pivot->subscription_type;
        }

        return 'monthly';
    }

    private function coreIsActive(?PlanModule $existingCore): bool
    {
        if (! $existingCore) {
            return false;
        }

        $pivot = $existingCore->pivot;
        $expiresAt = $pivot->expires_at ?? null;

        return ($pivot->status ?? null) === 'active'
            && (empty($expiresAt) || Carbon::parse($expiresAt)->isFuture());
    }

    /**
     * @param  array<int, array{module_id: int, period: string}>  $modulesInput
     * @return array<int, array{module_id: int, period: string}>
     */
    private function alignAddonPeriodsWithCore(
        array $modulesInput,
        SupportCollection $modules,
        string $corePlan,
    ): array {
        foreach ($modulesInput as $item) {
            $module = $modules->get($item['module_id'] ?? null);
            if (! $module || $module->is_core) {
                continue;
            }

            if ($item['period'] !== $corePlan) {
                throw new RuntimeException(
                    'دوره اشتراک افزونه‌ها باید با ماژول پایه ('.$this->periodLabel($corePlan).') یکسان باشد.'
                );
            }
        }

        return $modulesInput;
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'semi_annual' => '۶ ماهه',
            'annual' => 'سالانه',
            default => 'ماهانه',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $paymentModules
     */
    protected function provisionPurchasedModules(Tenant $tenant, array $paymentModules): void
    {
        $moduleIds = collect($paymentModules)->pluck('id')->filter()->all();

        if ($moduleIds === []) {
            return;
        }

        $hasSmsModule = PlanModule::query()
            ->whereIn('id', $moduleIds)
            ->where('slug', 'mod-sms')
            ->exists();

        if ($hasSmsModule) {
            $this->smsProvisioning->ensureDraftAccount($tenant);
        }
    }

    private function resolveCoreExpiresAt(?PlanModule $existingCore, bool $isCoreInRequest, int $durationDays): Carbon
    {
        $coreExpiresAt = null;

        if ($existingCore && $existingCore->pivot?->expires_at) {
            $pivotExpiresAt = $existingCore->pivot->expires_at;
            $coreExpiresAt = $pivotExpiresAt instanceof \DateTimeInterface
                ? Carbon::instance($pivotExpiresAt)
                : Carbon::parse($pivotExpiresAt);
        }

        $coreStart = $coreExpiresAt && $coreExpiresAt->isFuture() ? $coreExpiresAt->copy() : now();

        if ($isCoreInRequest) {
            return $coreStart->copy()->addDays($durationDays);
        }

        if ($coreExpiresAt && $coreExpiresAt->isFuture()) {
            return $coreExpiresAt->copy();
        }

        return now()->copy()->addDays($durationDays);
    }
}
