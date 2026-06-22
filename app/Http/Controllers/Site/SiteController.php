<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\SeoService;
use App\Services\SiteDataService;
use Illuminate\View\View;

abstract class SiteController extends Controller
{
    public function __construct(
        protected SiteDataService $siteData,
        protected SeoService $seo,
    ) {}

    protected function render(string $view, array $data = []): View
    {
        return view($view, array_merge($this->siteData->sharedViewData(), $data));
    }
}
