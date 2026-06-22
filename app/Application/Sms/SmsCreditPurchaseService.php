<?php

namespace App\Application\Sms;

use App\Infrastructure\Persistence\Eloquent\Models\SmsCreditOrder;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsAccount;
use App\Infrastructure\Services\Sms\IppanelAgencyService;
use App\Infrastructure\Services\Subscription\MockPaymentGateway;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SmsCreditPurchaseService
{
    public function __construct(
        protected IppanelAgencyService $agency,
        protected MockPaymentGateway $paymentGateway,
        protected TenantSmsProvisioningService $provisioning,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listPackages(): Collection
    {
        return collect($this->agency->listUserPackages())
            ->filter(fn ($pkg) => ($pkg['acl_type'] ?? '') === 'user' && ($pkg['status'] ?? '') === 'active')
            ->map(fn ($pkg) => [
                'id' => (int) ($pkg['acl_role_id'] ?? 0),
                'name' => $pkg['acl_role_name'] ?? 'بسته',
                'price' => (int) ($pkg['price'] ?? 0),
                'discount' => (int) ($pkg['special_disc'] ?? 0),
                'min_charge' => (int) ($pkg['min_sms_charge'] ?? 0),
            ])
            ->values();
    }

    public function purchase(Tenant $tenant, User $user, int $packageId): SmsCreditOrder
    {
        $account = TenantSmsAccount::where('tenant_id', $tenant->id)->first();

        if (! $account?->isActive()) {
            throw new RuntimeException('پنل پیامک فعال نیست. ابتدا درخواست خود را ثبت کنید.');
        }

        $package = $this->listPackages()->firstWhere('id', $packageId);

        if (! $package) {
            throw new RuntimeException('بسته شارژ انتخاب‌شده معتبر نیست.');
        }

        $amount = (float) $package['price'];

        $payment = $this->paymentGateway->charge($amount, [
            'tenant_id' => $tenant->id,
            'type' => 'sms_credit',
            'package_id' => $packageId,
        ]);

        return DB::transaction(function () use ($tenant, $user, $package, $packageId, $amount, $payment, $account) {
            $order = SmsCreditOrder::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'ippanel_package_id' => $packageId,
                'package_name' => $package['name'],
                'amount' => $amount,
                'status' => SmsCreditOrder::STATUS_PENDING,
            ]);

            try {
                $ippanelResponse = $this->agency->assignPackageToUser(
                    (int) $account->ippanel_user_id,
                    $packageId,
                );

                $order->update([
                    'status' => SmsCreditOrder::STATUS_PAID,
                    'gateway_reference' => $payment['transaction_id'] ?? null,
                    'ippanel_response' => $ippanelResponse,
                    'paid_at' => now(),
                ]);

                $this->provisioning->syncCredit($account);
            } catch (\Throwable $e) {
                $order->update([
                    'status' => SmsCreditOrder::STATUS_FAILED,
                    'ippanel_response' => ['error' => $e->getMessage()],
                ]);

                throw $e;
            }

            return $order->fresh();
        });
    }
}
