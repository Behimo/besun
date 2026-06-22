<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    use ChecksCrmAccess;

    public function index(): JsonResponse
    {
        $this->requirePermission('products.read');

        $categories = ProductCategory::query()
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('products.create');

        $tenantId = $this->crmTenantId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('product_categories', 'slug')->where('tenant_id', $tenantId)],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
        ]);

        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data['name'], $tenantId);
        $data['source'] = 'manual';

        $category = ProductCategory::create($data);

        return response()->json(['category' => $category], 201);
    }

    public function update(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $this->requirePermission('products.update');

        if ($productCategory->source === 'woocommerce') {
            abort(422, 'دسته‌بندی‌های ووکامرس فقط از طریق همگام‌سازی به‌روز می‌شوند.');
        }

        $tenantId = $this->crmTenantId();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('product_categories', 'slug')->where('tenant_id', $tenantId)->ignore($productCategory->id)],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
        ]);

        $productCategory->update($data);

        return response()->json(['category' => $productCategory->fresh()]);
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        $this->requirePermission('products.delete');

        if ($productCategory->source === 'woocommerce') {
            abort(422, 'دسته‌بندی‌های ووکامرس را از طریق همگام‌سازی مدیریت کنید.');
        }

        $productCategory->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    protected function uniqueSlug(string $name, int $tenantId, ?int $excludeId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $counter = 1;

        while (true) {
            $query = ProductCategory::query()->where('tenant_id', $tenantId)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (! $query->exists()) {
                return $slug;
            }
            $slug = $base.'-'.$counter;
            $counter++;
        }
    }
}
