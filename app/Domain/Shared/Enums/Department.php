<?php

namespace App\Domain\Shared\Enums;

enum Department: string
{
    case Sales = 'sales';
    case Marketing = 'marketing';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'فروش',
            self::Marketing => 'بازاریابی',
            self::Finance => 'مالی',
        };
    }

    public static function tryFromRole(RoleName $role): ?self
    {
        return $role->department();
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $d) => [
            'value' => $d->value,
            'label' => $d->label(),
        ], self::cases());
    }
}
