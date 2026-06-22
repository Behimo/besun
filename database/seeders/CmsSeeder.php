<?php

namespace Database\Seeders;

use App\Models\CmsAdmin;
use App\Models\CmsCategory;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsProduct;
use App\Services\HomeContentService;
use App\Services\SiteDataService;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        CmsAdmin::query()->updateOrCreate(
            ['email' => mb_strtolower(trim((string) env('CMS_ADMIN_EMAIL', 'admin@bisan.ir')))],
            [
                'name' => 'مدیر سایت',
                'password' => trim((string) env('CMS_ADMIN_PASSWORD', 'password')),
            ]
        );

        $siteData = app(SiteDataService::class);
        $homeContent = app(HomeContentService::class);

        foreach ($siteData->defaultPageMeta() as $slug => $meta) {
            CmsPage::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $meta['title'],
                    'meta_title' => $meta['title'],
                    'meta_description' => $meta['description'],
                    'meta_keywords' => $meta['keywords'] ?? null,
                    'robots' => 'index, follow',
                    'is_published' => true,
                    'is_system' => true,
                    'template' => 'system',
                    'sort_order' => match ($slug) {
                        'home' => 1,
                        'products' => 2,
                        'services' => 3,
                        'why-bisan' => 4,
                        'about' => 5,
                        'contact' => 6,
                        default => 99,
                    },
                ]
            );
        }

        $homePage = CmsPage::query()->where('slug', 'home')->first();
        if ($homePage && empty($homePage->content)) {
            $homePage->update([
                'content' => [
                    'hero' => $homeContent->defaultHero(),
                    'stats' => $siteData->stats(),
                    'why_bisan' => $siteData->whyBisan(),
                    'partners' => $siteData->partners(),
                    'testimonials' => $homeContent->defaultTestimonials(),
                    'trust_badges' => $homeContent->defaultTrustBadges(),
                    'cta' => $homeContent->defaultCta(),
                ],
            ]);
        }

        foreach ($siteData->defaultProducts() as $i => $product) {
            CmsProduct::query()->updateOrCreate(
                ['slug' => $product['slug']],
                [
                    'title' => $product['title'],
                    'subtitle' => $product['subtitle'],
                    'description' => $product['description'],
                    'accent' => $product['accent'],
                    'visual' => $product['visual'],
                    'audience' => $product['audience'],
                    'features' => $product['features'],
                    'cta' => $product['cta'],
                    'is_published' => true,
                    'is_featured' => $product['is_featured'] ?? false,
                    'sort_order' => $i + 1,
                ]
            );
        }

        $category = CmsCategory::query()->updateOrCreate(
            ['slug' => 'guides'],
            ['name' => 'راهنماها', 'description' => 'راهنماهای کاربردی', 'sort_order' => 1]
        );

        $samplePosts = [
            [
                'slug' => 'crm-for-small-business',
                'title' => 'چرا هر کسب‌وکار کوچکی به CRM نیاز دارد؟',
                'excerpt' => 'مدیریت مشتری با اکسل و واتساپ دیگر جواب نمی‌دهد. در این مقاله می‌بینید CRM چه کمکی می‌کند.',
                'body' => '<p>وقتی تعداد مشتریان و پیگیری‌ها زیاد می‌شود، ابزارهای دستی دیگر کافی نیستند. CRM به شما کمک می‌کند هیچ تماسی فراموش نشود و عملکرد تیم فروش شفاف باشد.</p><h2>سه نشانه که وقت CRM رسیده</h2><ul><li>پیگیری‌ها در واتساپ و اکسل گم می‌شوند</li><li>نمی‌دانید کدام فروشنده بهتر کار می‌کند</li><li>گزارش‌گیری ساعت‌ها وقت می‌گیرد</li></ul><p>راهبر CRM بیسان برای همین ساخته شده — ساده، فارسی و آماده استفاده.</p>',
            ],
            [
                'slug' => 'online-booking-benefits',
                'title' => '۵ مزیت نوبت‌دهی آنلاین برای کسب‌وکار خدماتی',
                'excerpt' => 'کلینیک، سالن یا آموزشگاه — نوبت آنلاین هم برای شما راحت‌تر است هم برای مشتری.',
                'body' => '<p>مشتریان امروز انتظار دارند ۲۴ ساعته نوبت بگیرند. نوژارو این کار را بدون پیچیدگی فنی ممکن می‌کند.</p><h2>مزایای کلیدی</h2><ul><li>کاهش تماس‌های تلفنی تکراری</li><li>پر شدن نوبت‌های خالی</li><li>مدیریت پرسنل و درآمد در یک داشبورد</li></ul>',
            ],
            [
                'slug' => 'wordpress-crm-integration',
                'title' => 'اتصال وردپرس به CRM: دیگر لید گم نشود',
                'excerpt' => 'هر فرم تماس و سفارش ووکامرس می‌تواند خودکار در CRM ثبت شود.',
                'body' => '<p>اگر سایت وردپرسی دارید و مشتریان را دستی پیگیری می‌کنید، افزونه بیسان پل ارتباطی بین سایت و راهبر CRM است.</p><p>نصب ساده، همگام‌سازی لحظه‌ای و بدون کار دستی.</p>',
            ],
        ];

        foreach ($samplePosts as $i => $post) {
            CmsPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'category_id' => $category->id,
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'body' => $post['body'],
                    'author' => 'تیم بیسان',
                    'is_published' => true,
                    'published_at' => now()->subDays($i + 1),
                    'meta_title' => $post['title'].' | بلاگ بیسان',
                    'meta_description' => $post['excerpt'],
                ]
            );
        }
    }
}
