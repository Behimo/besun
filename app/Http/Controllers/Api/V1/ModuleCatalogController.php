<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\PlanModule;
use Illuminate\Http\JsonResponse;

class ModuleCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = config('crm_modules.categories', []);

        $modules = PlanModule::query()
            ->where('is_active', true)
            ->orderByDesc('is_core')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PlanModule $module) => $this->formatModule($module));

        $grouped = $modules
            ->groupBy(fn (array $m) => $m['category'] ?? 'other')
            ->map(fn ($items, $key) => [
                'key' => $key,
                'label' => $categories[$key] ?? $key,
                'modules' => $items->values(),
            ])
            ->sortBy(fn ($g) => $g['modules']->first()['sort_order'] ?? 999)
            ->values();

        return response()->json([
            'modules' => $modules,
            'categories' => $categories,
            'grouped' => $grouped,
        ]);
    }

    private function formatModule(PlanModule $module): array
    {
        $config = collect(config('crm_modules.modules', []))
            ->firstWhere('slug', $module->slug) ?? [];

        return [
            'id' => $module->id,
            'name' => $module->name,
            'slug' => $module->slug,
            'description' => $module->description,
            'features' => $module->features ?? [],
            'category' => $module->category,
            'sort_order' => $module->sort_order ?? 0,
            'nav_route' => $module->nav_route,
            'icon' => $module->icon,
            'is_core' => $module->is_core,
            'requires_modules' => $config['requires_modules'] ?? [],
            'optional_modules' => $config['optional_modules'] ?? [],
            'monthly_price' => $module->monthly_price ?? $module->price,
            'semi_annual_price' => $module->semi_annual_price,
            'annual_price' => $module->annual_price,
            'seat_monthly_price' => $module->seat_monthly_price ?? $module->monthly_price ?? $module->price,
            'seat_semi_annual_price' => $module->seat_semi_annual_price,
            'seat_annual_price' => $module->seat_annual_price,
        ];
    }
}
