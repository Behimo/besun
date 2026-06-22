<?php

namespace App\Http\Controllers\Site;

use Illuminate\View\View;

class PageController extends SiteController
{
    public function show(string $slug): View
    {
        $page = $this->siteData->dynamicPage($slug);

        abort_unless($page, 404);

        $seo = $this->seo->forPage($slug, [
            'title' => $page->meta_title ?: $page->title,
            'description' => $page->meta_description,
            'keywords' => $page->meta_keywords,
            'og_image' => $page->og_image,
        ]);

        return $this->render('pages.dynamic', [
            'seo' => $seo,
            'page' => $page,
            'bodyHtml' => $page->content['body_html'] ?? '',
            'structuredData' => [
                $this->seo->breadcrumbSchema([
                    ['name' => 'خانه', 'url' => route('home')],
                    ['name' => $page->title, 'url' => route('pages.show', $page->slug)],
                ]),
                $this->seo->webPageSchema($seo['title'], $seo['description'], route('pages.show', $page->slug)),
            ],
        ]);
    }

    public function services(): View
    {
        $seo = $this->seo->forPage('services');

        return $this->render('pages.services', [
            'seo' => $seo,
            'services' => $this->siteData->services(),
            'process' => $this->siteData->serviceProcess(),
            'industries' => $this->siteData->serviceIndustries(),
            'structuredData' => [
                $this->seo->breadcrumbSchema([
                    ['name' => 'خانه', 'url' => route('home')],
                    ['name' => 'پروژه اختصاصی', 'url' => route('services')],
                ]),
                $this->seo->webPageSchema($seo['title'], $seo['description'], route('services')),
            ],
        ]);
    }

    public function about(): View
    {
        $seo = $this->seo->forPage('about');

        return $this->render('pages.about', [
            'seo' => $seo,
            'aboutTimeline' => $this->siteData->aboutTimeline(),
            'aboutPillars' => $this->siteData->aboutPillars(),
            'aboutMission' => $this->siteData->aboutMission(),
            'stats' => $this->siteData->stats(),
            'partners' => $this->siteData->partners(),
            'structuredData' => [
                $this->seo->breadcrumbSchema([
                    ['name' => 'خانه', 'url' => route('home')],
                    ['name' => 'درباره ما', 'url' => route('about')],
                ]),
                $this->seo->webPageSchema($seo['title'], $seo['description'], route('about')),
            ],
        ]);
    }

    public function whyBisan(): View
    {
        $seo = $this->seo->forPage('why-bisan');

        return $this->render('pages.why-bisan', [
            'seo' => $seo,
            'whyBisan' => $this->siteData->whyBisan(),
            'structuredData' => [
                $this->seo->breadcrumbSchema([
                    ['name' => 'خانه', 'url' => route('home')],
                    ['name' => 'چرا بیسان؟', 'url' => route('why-bisan')],
                ]),
                $this->seo->webPageSchema($seo['title'], $seo['description'], route('why-bisan')),
            ],
        ]);
    }
}
