<?php

namespace App\Domain\Shared\Enums;

enum RoleName: string
{
    case Owner = 'owner';
    case SalesManager = 'sales_manager';
    case MarketingManager = 'marketing_manager';
    case FinanceManager = 'finance_manager';
    case SalesEmployee = 'sales_employee';
    case MarketingEmployee = 'marketing_employee';
    case FinanceEmployee = 'finance_employee';

    /** @deprecated migrated to sales_manager */
    case Admin = 'admin';
    /** @deprecated migrated to sales_employee */
    case Employee = 'employee';
    /** @deprecated migrated to marketing_employee */
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'مالک مجموعه',
            self::SalesManager => 'مدیر فروش',
            self::MarketingManager => 'مدیر بازاریابی',
            self::FinanceManager => 'مدیر مالی',
            self::SalesEmployee => 'کارمند فروش',
            self::MarketingEmployee => 'کارمند بازاریابی',
            self::FinanceEmployee => 'کارمند مالی',
            self::Admin => 'مدیر (قدیمی)',
            self::Employee => 'کارمند (قدیمی)',
            self::Support => 'پشتیبانی (قدیمی)',
        };
    }

    public function department(): ?Department
    {
        return match ($this) {
            self::SalesManager, self::SalesEmployee, self::Admin => Department::Sales,
            self::MarketingManager, self::MarketingEmployee, self::Support => Department::Marketing,
            self::FinanceManager, self::FinanceEmployee => Department::Finance,
            default => null,
        };
    }

    public function isManager(): bool
    {
        return in_array($this, [
            self::Owner,
            self::SalesManager,
            self::MarketingManager,
            self::FinanceManager,
            self::Admin,
        ], true);
    }

    public function parentRole(): ?self
    {
        return match ($this) {
            self::SalesEmployee => self::SalesManager,
            self::MarketingEmployee => self::MarketingManager,
            self::FinanceEmployee => self::FinanceManager,
            default => null,
        };
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    public static function tryFromValue(?string $value): ?self
    {
        if (! $value) {
            return null;
        }

        return self::tryFrom($value);
    }

    public static function assignableForInvite(): array
    {
        return [
            self::SalesManager,
            self::MarketingManager,
            self::FinanceManager,
            self::SalesEmployee,
            self::MarketingEmployee,
            self::FinanceEmployee,
        ];
    }

    public static function assignableValues(): array
    {
        return array_map(fn (self $r) => $r->value, self::assignableForInvite());
    }

    public static function manageableValues(): array
    {
        return array_map(fn (self $r) => $r->value, [
            self::Owner,
            ...self::assignableForInvite(),
        ]);
    }

    public static function fromLegacy(string $role): self
    {
        return match ($role) {
            'admin' => self::SalesManager,
            'employee' => self::SalesEmployee,
            'support' => self::MarketingEmployee,
            'owner' => self::Owner,
            default => self::tryFrom($role) ?? self::SalesEmployee,
        };
    }

    /** @return array<int, array{value: string, label: string, department: ?string}> */
    public static function options(): array
    {
        $roles = [self::Owner, ...self::assignableForInvite()];

        return array_map(fn (self $r) => [
            'value' => $r->value,
            'label' => $r->label(),
            'department' => $r->department()?->value,
        ], $roles);
    }
}
