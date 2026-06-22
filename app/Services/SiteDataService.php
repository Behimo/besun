<?php

namespace App\Services;

use App\Models\CmsPage;
use App\Models\CmsProduct;
use App\Models\CmsSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SiteDataService
{
    public function navLinks(): array
    {
        $links = [
            ['route' => 'home', 'label' => 'خانه'],
            ['route' => 'products.index', 'label' => 'محصولات'],
            ['route' => 'services', 'label' => 'پروژه اختصاصی'],
            ['route' => 'why-bisan', 'label' => 'چرا بیسان؟'],
            ['route' => 'blog.index', 'label' => 'بلاگ'],
            ['route' => 'about', 'label' => 'درباره ما'],
            ['route' => 'contact', 'label' => 'تماس'],
        ];

        $dynamic = CmsPage::query()
            ->published()
            ->where('show_in_nav', true)
            ->where('is_system', false)
            ->orderBy('sort_order')
            ->get();

        foreach ($dynamic as $page) {
            $links[] = [
                'route' => 'pages.show',
                'params' => ['slug' => $page->slug],
                'label' => $page->title,
            ];
        }

        return $links;
    }

    public function pageContent(string $slug, array $defaults = []): array
    {
        $page = CmsPage::query()->published()->where('slug', $slug)->first();

        if (! $page || empty($page->content)) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $page->content);
    }

    public function dynamicPage(string $slug): ?CmsPage
    {
        return CmsPage::query()
            ->published()
            ->where('slug', $slug)
            ->where('is_system', false)
            ->first();
    }

    public function contact(): array
    {
        return [
            'email' => CmsSetting::get('contact_email', config('cms.contact_email')),
            'phone' => CmsSetting::get('contact_phone', config('cms.contact_phone')),
        ];
    }

    public function heroPills(): array
    {
        return [
            ['title' => 'فروش بیشتر', 'text' => 'مشتری را گم نکنید — از تماس اول تا قرارداد'],
            ['title' => 'کار منظم‌تر', 'text' => 'نوبت، پرسنل و درآمد — بدون کاغذ و حدس'],
            ['title' => 'سایت به CRM', 'text' => 'لیدهای سایت خودکار وارد سیستم شما شود'],
        ];
    }

    public function services(): array
    {
        return [
            [
                'title' => 'نرم‌افزار مخصوص شما',
                'desc' => 'وقتی محصول آماده جواب نمی‌دهد، دقیقاً همان چیزی را می‌سازیم که کسب‌وکارتان نیاز دارد.',
                'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4',
            ],
            [
                'title' => 'وصل کردن سیستم‌ها',
                'desc' => 'سایت، فروشگاه و CRM شما با هم حرف بزنند — بدون کار دستی و بدون خطا.',
                'icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
            ],
            [
                'title' => 'نسخه اولیه سریع',
                'desc' => 'ایده‌تان را زود به محصول تبدیل کنید — برای تست بازار یا جذب سرمایه.',
                'icon' => 'M13 10V3L4 14h7v7l9-11h-7z',
            ],
            [
                'title' => 'تیم برنامه‌نویس اختصاصی',
                'desc' => 'یک تیم کنار شما می‌ماند — توسعه، به‌روزرسانی و پشتیبانی مداوم.',
                'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
            ],
        ];
    }

    public function whyBisan(): array
    {
        return [
            [
                'tag' => '۰۱ · سادگی',
                'title' => 'یک تیم، یک پاسخ',
                'desc' => 'محصول آماده می‌خواهید یا پروژه اختصاصی — شما نتیجه می‌گیرید، نه دردسر فنی.',
                'accent' => 'orange',
                'visual' => 'ecosystem',
                'size' => 'lg',
            ],
            [
                'tag' => '۰۲ · سرعت',
                'title' => 'همین هفته شروع کنید',
                'desc' => 'بدون سرور و نصب پیچیده. تیم‌تان آموزش می‌گیرد و کار را شروع می‌کند.',
                'accent' => 'blue',
                'visual' => 'speed',
                'size' => 'sm',
            ],
            [
                'tag' => '۰۳ · شفافیت',
                'title' => 'ببینید چه خبر است',
                'desc' => 'فروش، عملکرد تیم و درآمد — گزارش‌هایی که واقعاً به تصمیم‌گیری کمک می‌کنند.',
                'accent' => 'purple',
                'visual' => 'analytics',
                'size' => 'md',
            ],
            [
                'tag' => '۰۴ · اطمینان',
                'title' => 'کنار شما می‌مانیم',
                'desc' => 'از اولین تماس تا بعد از تحویل — پشتیبانی فارسی، واقعی و در دسترس.',
                'accent' => 'gradient',
                'visual' => 'support',
                'size' => 'wide',
            ],
        ];
    }

    public function defaultProducts(): array
    {
        return [
            [
                'slug' => 'rahbar',
                'title' => 'راهبر',
                'subtitle' => 'Rahbar CRM',
                'description' => 'مشتری را گم نکنید. از اولین تماس تا امضای قرارداد، همه‌چیز یکجا.',
                'accent' => 'orange',
                'visual' => 'crm',
                'audience' => 'تیم فروش و بازاریابی',
                'features' => ['هیچ پیگیری فراموش نمی‌شود', 'ببینید کدام فروشنده بهتر می‌فروشد', 'گزارش ساده — بدون اکسل'],
                'cta' => 'دمو رایگان راهبر',
                'is_featured' => true,
                'body' => "راهبر CRM سیستم مدیریت مشتری و فروش بیسان است که برای تیم‌های فروش ایرانی طراحی شده.\n\nاز لید اولیه تا قرارداد نهایی، هر مرحله قابل پیگیری است. قیف فروش، وظایف روزانه، گزارش عملکرد فروشندگان و یادآوری خودکار — همه در یک پنل ساده.\n\nاگر الان با اکسل یا واتساپ مشتریان را مدیریت می‌کنید، راهبر جایگزین منظم و قابل گزارش‌گیری است.",
                'highlights' => [
                    ['label' => 'قیف فروش', 'desc' => 'مراحل فروش را ببینید و گلوگاه‌ها را پیدا کنید'],
                    ['label' => 'یادآوری خودکار', 'desc' => 'هیچ پیگیری فراموش نمی‌شود'],
                    ['label' => 'گزارش تیم', 'desc' => 'عملکرد هر فروشنده شفاف و قابل مقایسه'],
                ],
            ],
            [
                'slug' => 'nojaro',
                'title' => 'نوژارو',
                'subtitle' => 'Nojaro',
                'description' => 'نوبت، پرسنل و درآمد — برای کلینیک، سالن، آموزشگاه و هر کسب‌وکار خدماتی.',
                'accent' => 'purple',
                'visual' => 'service',
                'audience' => 'کسب‌وکارهای خدماتی',
                'features' => ['مشتری آنلاین نوبت بگیرد', 'همه شعب را یکجا ببینید', 'درآمد هر روز، شفاف و روشن'],
                'cta' => 'دمو رایگان نوژارو',
                'is_featured' => false,
                'body' => "نوژارو برای کسب‌وکارهای خدماتی ساخته شده: کلینیک، سالن زیبایی، آموزشگاه، مشاوره و هر جایی که نوبت و پرسنل مهم است.\n\nمشتریان آنلاین نوبت می‌گیرند، پرسنل برنامه کاری خود را می‌بینند و مدیر درآمد روزانه هر شعبه را یکجا مشاهده می‌کند.\n\nدیگر نیازی به دفترچه نوبت یا پیام‌های پراکنده واتساپ نیست.",
                'highlights' => [
                    ['label' => 'نوبت آنلاین', 'desc' => 'مشتری ۲۴ ساعته نوبت رزرو می‌کند'],
                    ['label' => 'مدیریت پرسنل', 'desc' => 'برنامه کاری و درآمد هر نفر'],
                    ['label' => 'چند شعبه', 'desc' => 'همه واحدها در یک داشبورد'],
                ],
            ],
            [
                'slug' => 'wordpress-plugin',
                'title' => 'افزونه وردپرس',
                'subtitle' => 'WordPress Plugin',
                'description' => 'لیدهای سایت و فروشگاه مستقیم وارد CRM شود — بدون وارد کردن دستی.',
                'accent' => 'blue',
                'visual' => 'wordpress',
                'audience' => 'صاحبان سایت وردپرسی',
                'features' => ['فرم تماس و ووکامرس وصل می‌شود', 'مشتری و سفارش خودکار ثبت می‌شود', 'دیگر لید گم نمی‌شود'],
                'cta' => 'دمو رایگان افزونه',
                'is_featured' => false,
                'body' => "افزونه وردپرس بیسان پل ارتباطی بین سایت شما و CRM راهبر است.\n\nهر فرم تماس، ثبت‌نام خبرنامه یا سفارش ووکامرس به‌صورت خودکار در CRM ثبت می‌شود. دیگر لازم نیست لیدها را دستی کپی کنید یا در اکسل پیگیری کنید.\n\nنصب ساده، اتصال یک‌باره و همگام‌سازی لحظه‌ای.",
                'highlights' => [
                    ['label' => 'فرم تماس', 'desc' => 'Contact Form 7 و فرم‌های رایج'],
                    ['label' => 'ووکامرس', 'desc' => 'سفارش و مشتری خودکار در CRM'],
                    ['label' => 'همگام لحظه‌ای', 'desc' => 'بدون تأخیر و کار دستی'],
                ],
            ],
        ];
    }

    public function products(): Collection
    {
        return Cache::remember('cms_products', 3600, function () {
            $dbProducts = CmsProduct::query()->published()->orderBy('sort_order')->get();

            if ($dbProducts->isNotEmpty()) {
                return $dbProducts->map(fn (CmsProduct $p) => $this->formatProduct($p));
            }

            return collect($this->defaultProducts())->map(function (array $p) {
                $p['href'] = route('products.show', $p['slug']);

                return $p;
            });
        });
    }

    public function product(string $slug): ?array
    {
        $product = CmsProduct::query()->published()->where('slug', $slug)->first();

        if ($product) {
            return $this->formatProduct($product);
        }

        $default = collect($this->defaultProducts())->firstWhere('slug', $slug);

        if ($default) {
            $default['href'] = route('products.show', $slug);

            return $default;
        }

        return null;
    }

    public function aboutTimeline(): array
    {
        return [
            ['year' => '۲۰۱۴', 'title' => 'شروع کار', 'desc' => 'ساخت نرم‌افزار برای کسب‌وکارهای ایرانی'],
            ['year' => '۲۰۱۹', 'title' => 'محصولات خودمان', 'desc' => 'راهبر و نوژارو — راه‌حل‌هایی که خودمان هم از آن‌ها استفاده می‌کنیم'],
            ['year' => '۲۰۲۴', 'title' => 'بیسان امروز', 'desc' => '۳ محصول فعال + پروژه اختصاصی برای مشتریان'],
        ];
    }

    public function aboutPillars(): array
    {
        return [
            ['icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', 'text' => 'محصول آماده یا پروژه اختصاصی — هر دو با یک تیم'],
            ['icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'text' => '۳ محصول که هر روز در دست مشتریان واقعی است'],
            ['icon' => 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z', 'text' => 'ساده برای شما — پیچیدگی فنی را ما انجام می‌دهیم'],
            ['icon' => 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'پشتیبانی فارسی — از روز اول تا سال‌ها بعد'],
        ];
    }

    public function stats(): array
    {
        return [
            ['value' => '+۱۲۰۰', 'label' => 'کسب‌وکار', 'hint' => 'به ما اعتماد کرده‌اند', 'icon' => 'business'],
            ['value' => '+۹۸٪', 'label' => 'رضایت', 'hint' => 'از مشتریان فعلی', 'icon' => 'heart'],
            ['value' => '+۱۰', 'label' => 'سال', 'hint' => 'تجربه در بازار ایران', 'icon' => 'clock'],
            ['value' => '۲۴/۷', 'label' => 'پشتیبانی', 'hint' => 'وقتی به ما نیاز دارید', 'icon' => 'support'],
        ];
    }

    public function partners(): array
    {
        return ['ایران‌سرور', 'جاب‌ویژن', 'تریبون', 'دیدار', 'ژاکت'];
    }

    public function serviceProcess(): array
    {
        return [
            ['step' => '۱', 'title' => 'شنیدن نیاز شما', 'desc' => 'در یک جلسه کوتاه، دقیقاً می‌فهمیم چه مشکلی دارید و چه نتیجه‌ای می‌خواهید.'],
            ['step' => '۲', 'title' => 'طراحی راه‌حل', 'desc' => 'معماری، زمان‌بندی و هزینه را شفاف ارائه می‌دهیم — بدون اصطلاحات پیچیده.'],
            ['step' => '۳', 'title' => 'ساخت و تحویل', 'desc' => 'نسخه اولیه سریع، بازخورد شما، و تکرار تا رسیدن به محصول نهایی.'],
            ['step' => '۴', 'title' => 'پشتیبانی مداوم', 'desc' => 'بعد از تحویل هم کنار شما می‌مانیم — به‌روزرسانی، رفع باگ و توسعه.'],
        ];
    }

    public function serviceIndustries(): array
    {
        return [
            'کلینیک و سلامت', 'آموزش و مشاوره', 'تولید و توزیع',
            'املاک و ساختمان', 'خدمات مالی', 'فروشگاه آنلاین',
        ];
    }

    public function productComparison(): array
    {
        return [
            ['feature' => 'مدیریت مشتری و فروش', 'rahbar' => true, 'nojaro' => false, 'wordpress' => false],
            ['feature' => 'نوبت‌دهی و پرسنل', 'rahbar' => false, 'nojaro' => true, 'wordpress' => false],
            ['feature' => 'اتصال سایت به CRM', 'rahbar' => false, 'nojaro' => false, 'wordpress' => true],
            ['feature' => 'گزارش و داشبورد', 'rahbar' => true, 'nojaro' => true, 'wordpress' => false],
        ];
    }

    public function contactFaq(): array
    {
        return [
            ['q' => 'مشاوره رایگان است؟', 'a' => 'بله. اولین تماس و جلسه مشاوره کاملاً رایگان است تا بفهمیم کدام راه برای شما مناسب‌تر است.'],
            ['q' => 'چقدر طول می‌کشد تا شروع کنیم؟', 'a' => 'محصولات آماده معمولاً در ۳ روز کاری راه‌اندازی می‌شوند. پروژه اختصاصی بسته به پیچیدگی، از چند هفته شروع می‌شود.'],
            ['q' => 'پشتیبانی فارسی دارید؟', 'a' => 'بله. تیم پشتیبانی ما فارسی‌زبان است و در ساعات کاری پاسخگوست.'],
            ['q' => 'آیا می‌توانم محصول را قبل از خرید ببینم؟', 'a' => 'حتماً. دمو رایگان برای هر سه محصول داریم — کافی است فرم تماس را پر کنید.'],
        ];
    }

    public function aboutMission(): array
    {
        return [
            'mission' => 'ساخت نرم‌افزاری که کسب‌وکارهای ایرانی واقعاً از آن استفاده کنند — نه پیچیده، نه گران، نه دور از نیاز.',
            'vision' => 'هر کسب‌وکار ایرانی، ابزار دیجیتال درست برای رشد خودش را داشته باشد.',
        ];
    }

    public function pageMeta(string $slug): array
    {
        $defaults = $this->defaultPageMeta()[$slug] ?? [];
        $page = CmsPage::query()->published()->where('slug', $slug)->first();

        if (! $page) {
            return $defaults;
        }

        return array_filter([
            'title' => $page->meta_title ?: $page->title,
            'description' => $page->meta_description ?: ($defaults['description'] ?? null),
            'keywords' => $page->meta_keywords ?: ($defaults['keywords'] ?? null),
            'og_title' => $page->meta_title ?: $page->title,
            'og_image' => $page->og_image,
            'robots' => $page->robots ?: ($defaults['robots'] ?? 'index, follow'),
        ]);
    }

    public function defaultPageMeta(): array
    {
        return [
            'home' => [
                'title' => 'بیسان | BISAN Holding',
                'description' => 'بیسان — ۳ محصول آماده برای فروش و خدمات، یا پروژه اختصاصی برای کسب‌وکار شما. مشاوره رایگان.',
                'keywords' => 'بیسان, BISAN, CRM, راهبر, نوژارو, نرم‌افزار, ایران',
                'og_title' => 'بیسان — نرم‌افزار درست برای رشد کسب‌وکار شما',
            ],
            'products' => [
                'title' => 'محصولات بیسان | راهبر، نوژارو، افزونه وردپرس',
                'description' => 'محصولات نرم‌افزاری بیسان: راهبر CRM، نوژارو برای کسب‌وکارهای خدماتی و افزونه وردپرس برای اتصال سایت به CRM.',
                'keywords' => 'راهبر CRM, نوژارو, افزونه وردپرس, نرم‌افزار فروش, بیسان',
                'og_title' => 'محصولات بیسان — راه‌حل‌های آماده برای رشد',
            ],
            'services' => [
                'title' => 'پروژه اختصاصی | توسعه نرم‌افزار سفارشی — بیسان',
                'description' => 'نرم‌افزار مخصوص کسب‌وکار شما، اتصال سیستم‌ها، MVP سریع و تیم برنامه‌نویسی اختصاصی.',
                'keywords' => 'نرم‌افزار سفارشی, توسعه نرم‌افزار, MVP, اتصال سیستم, بیسان',
                'og_title' => 'پروژه اختصاصی — برای شما می‌سازیم',
            ],
            'about' => [
                'title' => 'درباره بیسان | ۱۰ سال تجربه در نرم‌افزار',
                'description' => 'بیسان تیم برنامه‌نویسی با ۳ محصول فعال و بیش از ۱۰ سال تجربه در بازار ایران.',
                'keywords' => 'درباره بیسان, BISAN Holding, تاریخچه, تیم',
                'og_title' => 'درباره بیسان — ۱۰ سال کنار شما',
            ],
            'contact' => [
                'title' => 'تماس با بیسان | مشاوره رایگان',
                'description' => 'با تیم بیسان تماس بگیرید. مشاوره رایگان برای انتخاب محصول یا پروژه اختصاصی.',
                'keywords' => 'تماس بیسان, مشاوره رایگان, پشتیبانی',
                'og_title' => 'تماس با بیسان — مشاوره رایگان',
            ],
            'why-bisan' => [
                'title' => 'چرا بیسان؟ | مزایای همکاری با ما',
                'description' => 'سادگی، سرعت، شفافیت و اطمینان — دلایلی که بیش از ۱۲۰۰ کسب‌وکار بیسان را انتخاب کرده‌اند.',
                'keywords' => 'چرا بیسان, مزایا, اعتماد, پشتیبانی',
                'og_title' => 'چرا بیسان؟ — ۴ دلیل برای انتخاب ما',
            ],
        ];
    }

    public function sharedViewData(): array
    {
        return [
            'navLinks' => $this->navLinks(),
            'contact' => $this->contact(),
        ];
    }

    public function clearCache(): void
    {
        Cache::forget('cms_products');
        Cache::forget('cms_home_content');
    }

    private function formatProduct(CmsProduct $product): array
    {
        return [
            'slug' => $product->slug,
            'title' => $product->title,
            'subtitle' => $product->subtitle,
            'description' => $product->description,
            'accent' => $product->accent,
            'visual' => $product->visual,
            'audience' => $product->audience,
            'features' => $product->features ?? [],
            'cta' => $product->cta,
            'body' => $product->body,
            'highlights' => $product->highlights ?? [],
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'meta_keywords' => $product->meta_keywords,
            'og_image' => $product->og_image,
            'href' => route('products.show', $product->slug),
            'is_featured' => $product->is_featured,
        ];
    }
}
