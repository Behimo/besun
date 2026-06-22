<?php

namespace App\Domain\Shared\Enums;

enum PlanInterval: string
{
    case Monthly = 'monthly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';
}
