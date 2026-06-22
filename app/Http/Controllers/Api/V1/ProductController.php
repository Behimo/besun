<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Quote\QuoteService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use ChecksCrmAccess;

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('products.read');

        $tenantId = $this->crmTenantId();

        $products = Product::query()
            ->with('category:id,name')
            ->when($request->q, fn ($q, $search) => $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->source, fn ($q, $source) => $q->where('source', $source))
            ->when($request->stock_status, fn ($q, $stock) => $q->where('stock_status', $stock))
            ->when($request->category_id, fn ($q, $id) => $q->where('product_category_id', $id))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('status', 'active'))
            ->latest()
            ->paginate((int) ($request->per_page ?? 20));

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('products.create');

        $tenantId = $this->crmTenantId();
        $data = $this->validatedProduct($request, $tenantId);
        $data['source'] = 'manual';
        $data['slug'] = $data['slug'] ?? QuoteService::uniqueSlug($data['name']);

        $product = Product::create($data);

        return response()->json(['product' => $product->load('category')], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->requirePermission('products.read');

        return response()->json(['product' => $product->load('category')]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->requirePermission('products.update');

        if ($product->source === 'woocommerce') {
            abort(422, 'محصولات همگام‌شده از ووکامرس فقط از طریق همگام‌سازی به‌روز می‌شوند.');
        }

        $tenantId = $this->crmTenantId();
        $data = $this->validatedProduct($request, $tenantId, $product->id, partial: true);

        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = QuoteService::uniqueSlug($data['name'], $product->id);
        }

        $product->update($data);

        return response()->json(['product' => $product->fresh()->load('category')]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->requirePermission('products.delete');

        if ($product->source === 'woocommerce') {
            abort(422, 'محصولات ووکامرس را از طریق قطع همگام‌سازی یا حذف در فروشگاه مدیریت کنید.');
        }

        $product->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    protected function validatedProduct(Request $request, int $tenantId, ?int $productId = null, bool $partial = false): array
    {
        $rules = [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->where('tenant_id', $tenantId)->ignore($productId)],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->where('tenant_id', $tenantId)->ignore($productId)],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'stock_status' => ['nullable', 'string', 'in:instock,outofstock,onbackorder'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'gallery' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:active,draft,archived'],
        ];

        $data = $request->validate($rules);

        if (! empty($data['sku'])) {
            $data['sku'] = trim($data['sku']);
        }

        return $data;
    }
}
