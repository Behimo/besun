<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsProduct;
use App\Services\SiteDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private SiteDataService $siteData) {}

    public function index(): View
    {
        $products = CmsProduct::query()->orderBy('sort_order')->get();

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => new CmsProduct]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);
        CmsProduct::query()->create($validated);
        $this->siteData->clearCache();

        return redirect()->route('admin.products.index')->with('success', 'محصول ایجاد شد.');
    }

    public function edit(CmsProduct $product): View
    {
        return view('admin.products.form', compact('product'));
    }

    public function update(Request $request, CmsProduct $product): RedirectResponse
    {
        $product->update($this->validateProduct($request));
        $this->siteData->clearCache();

        return redirect()->route('admin.products.index')->with('success', 'محصول به‌روزرسانی شد.');
    }

    public function destroy(CmsProduct $product): RedirectResponse
    {
        $product->delete();
        $this->siteData->clearCache();

        return redirect()->route('admin.products.index')->with('success', 'محصول حذف شد.');
    }

    private function validateProduct(Request $request): array
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'alpha_dash'],
            'title' => ['required', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'accent' => ['required', 'in:orange,purple,blue'],
            'visual' => ['nullable', 'string', 'max:50'],
            'audience' => ['nullable', 'string', 'max:200'],
            'features' => ['nullable', 'string'],
            'cta' => ['nullable', 'string', 'max:100'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:300'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['features'] = array_values(array_filter(
            array_map('trim', explode("\n", $validated['features'] ?? ''))
        ));
        $validated['is_published'] = $request->boolean('is_published');
        $validated['is_featured'] = $request->boolean('is_featured');

        return $validated;
    }
}
