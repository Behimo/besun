<?php

namespace Database\Seeders;

use App\Domain\Shared\Enums\PlanInterval;
use App\Infrastructure\Persistence\Eloquent\Models\Plan;
use App\Infrastructure\Persistence\Eloquent\Models\PlanModule;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'پلن ماهانه',
                'slug' => 'monthly',
                'interval' => PlanInterval::Monthly->value,
                'duration_months' => 1,
                'price' => 832200,
                'features' => ['مخاطبین', 'لیدها', 'معاملات', 'تسک‌ها', 'داشبورد'],
            ],
            [
                'name' => 'پلن ۶ ماهه',
                'slug' => 'semi-annual',
                'interval' => PlanInterval::SemiAnnual->value,
                'duration_months' => 6,
                'price' => 4161000,
                'features' => ['همه امکانات پایه', '۱۰٪ تخفیف', 'پشتیبانی اولویت‌دار'],
            ],
            [
                'name' => 'پلن سالانه',
                'slug' => 'annual',
                'interval' => PlanInterval::Annual->value,
                'duration_months' => 12,
                'price' => 7489800,
                'features' => ['همه امکانات پایه', '۲۰٪ تخفیف', 'پشتیبانی VIP'],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        $catalogSlugs = collect(config('crm_modules.modules', []))->pluck('slug')->all();

        foreach (config('crm_modules.modules', []) as $module) {
            PlanModule::updateOrCreate(
                ['slug' => $module['slug']],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'features' => $module['features'] ?? [],
                    'category' => $module['category'] ?? null,
                    'sort_order' => $module['sort_order'] ?? 0,
                    'nav_route' => $module['nav_route'] ?? null,
                    'icon' => $module['icon'] ?? null,
                    'price' => $module['price'],
                    'monthly_price' => $module['monthly_price'],
                    'semi_annual_price' => $module['semi_annual_price'],
                    'annual_price' => $module['annual_price'],
                    'seat_monthly_price' => $module['seat_monthly_price'] ?? null,
                    'seat_semi_annual_price' => $module['seat_semi_annual_price'] ?? null,
                    'seat_annual_price' => $module['seat_annual_price'] ?? null,
                    'is_core' => $module['is_core'] ?? false,
                    'is_active' => true,
                ],
            );
        }

        PlanModule::whereNotIn('slug', $catalogSlugs)->update(['is_active' => false]);
    }
}
