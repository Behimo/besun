<?php

namespace App\Http\Controllers\Site;

use Illuminate\View\View;

class ProductController extends SiteController
{
    public function index(): View
    {
        $seo = $this->seo->forPage('products');

        return $this->render('pages.products.index', [
            'seo' => $seo,
            'products' => $this->siteData->products(),
            'comparison' => $this->siteData->productComparison(),
            'structuredData' => [
                $this->seo->breadcrumbSchema([
                    ['name' => 'خانه', 'url' => route('home')],
                    ['name' => 'محصولات', 'url' => route('products.index')],
                ]),
                $this->seo->webPageSchema($seo['title'], $seo['description'], route('products.index')),
            ],
        ]);
    }

    public function show(string $slug): View
    {
        $product = $this->siteData->product($slug);

        abort_unless($product, 404);

        $seo = $this->seo->forProduct($product);

        return $this->render('pages.products.show', [
            'seo' => $seo,
            'product' => $product,
            'structuredData' => [
                $this->seo->breadcrumbSchema([
                    ['name' => 'خانه', 'url' => route('home')],
                    ['name' => 'محصولات', 'url' => route('products.index')],
                    ['name' => $product['title'], 'url' => route('products.show', $slug)],
                ]),
                $this->seo->productSchema($product),
                $this->seo->webPageSchema($seo['title'], $seo['description'], route('products.show', $slug)),
            ],
        ]);
    }
}
