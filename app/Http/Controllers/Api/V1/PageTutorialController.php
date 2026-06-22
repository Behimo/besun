<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\PageTutorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PageTutorialController extends Controller
{
    protected const CATALOG = [
        ['route_name' => 'dashboards-home', 'title' => 'داشبورد من', 'group' => 'حساب شخصی', 'sort_order' => 10],
        ['route_name' => 'apps-account-tenants', 'title' => 'مجموعه‌های من', 'group' => 'حساب شخصی', 'sort_order' => 20],
        ['route_name' => 'apps-account-modules', 'title' => 'فروشگاه ماژول', 'group' => 'حساب شخصی', 'sort_order' => 30],
        ['route_name' => 'apps-account-transactions', 'title' => 'تراکنش‌ها', 'group' => 'حساب شخصی', 'sort_order' => 40],
        ['route_name' => 'invitations', 'title' => 'دعوتنامه‌های من', 'group' => 'حساب شخصی', 'sort_order' => 50],
        ['route_name' => 'apps-profile', 'title' => 'پروفایل', 'group' => 'حساب شخصی', 'sort_order' => 60],
        ['route_name' => 'dashboards-crm', 'title' => 'پیشخوان مجموعه', 'group' => 'CRM', 'sort_order' => 100],
        ['route_name' => 'apps-crm-team-chat', 'title' => 'گفتگوی تیم', 'group' => 'CRM', 'sort_order' => 110],
        ['route_name' => 'apps-crm-campaigns', 'title' => 'کمپین‌ها', 'group' => 'بازاریابی', 'sort_order' => 200],
        ['route_name' => 'apps-crm-leads', 'title' => 'لیدها', 'group' => 'بازاریابی', 'sort_order' => 210],
        ['route_name' => 'apps-crm-marketing-funnel', 'title' => 'قیف بازاریابی', 'group' => 'بازاریابی', 'sort_order' => 220],
        ['route_name' => 'apps-crm-web-forms', 'title' => 'وب‌فرم', 'group' => 'بازاریابی', 'sort_order' => 230],
        ['route_name' => 'apps-crm-sms', 'title' => 'پنل پیامک', 'group' => 'بازاریابی', 'sort_order' => 240],
        ['route_name' => 'apps-crm-automation', 'title' => 'اتوماسیون', 'group' => 'بازاریابی', 'sort_order' => 250],
        ['route_name' => 'apps-crm-contacts', 'title' => 'مخاطبین', 'group' => 'فروش', 'sort_order' => 300],
        ['route_name' => 'apps-crm-deals', 'title' => 'قیف فروش', 'group' => 'فروش', 'sort_order' => 310],
        ['route_name' => 'apps-crm-sales-targets', 'title' => 'هدف‌گذاری', 'group' => 'فروش', 'sort_order' => 315],
        ['route_name' => 'apps-crm-reports', 'title' => 'گزارش فروش', 'group' => 'فروش', 'sort_order' => 320],
        ['route_name' => 'apps-crm-products', 'title' => 'کاتالوگ محصول', 'group' => 'فروش', 'sort_order' => 330],
        ['route_name' => 'apps-crm-tasks', 'title' => 'تسک‌ها', 'group' => 'فعالیت‌ها', 'sort_order' => 400],
        ['route_name' => 'apps-crm-daily-reports', 'title' => 'گزارش کار روزانه', 'group' => 'فعالیت‌ها', 'sort_order' => 410],
        ['route_name' => 'apps-crm-activities', 'title' => 'ثبت فعالیت', 'group' => 'فعالیت‌ها', 'sort_order' => 420],
        ['route_name' => 'apps-crm-projects', 'title' => 'پروژه و کارت', 'group' => 'عملیات', 'sort_order' => 500],
        ['route_name' => 'apps-crm-ticketing', 'title' => 'تیکتینگ', 'group' => 'پشتیبانی', 'sort_order' => 600],
        ['route_name' => 'apps-crm-invoicing', 'title' => 'فاکتور و پرداخت', 'group' => 'مالی', 'sort_order' => 700],
        ['route_name' => 'apps-crm-bi', 'title' => 'هوش تجاری BI', 'group' => 'گزارش و تحلیل', 'sort_order' => 800],
        ['route_name' => 'apps-crm-integrations', 'title' => 'یکپارچگی', 'group' => 'یکپارچگی', 'sort_order' => 900],
        ['route_name' => 'apps-crm-users', 'title' => 'کاربران و دعوت', 'group' => 'مدیریت', 'sort_order' => 1000],
        ['route_name' => 'apps-tenant-settings', 'title' => 'تنظیمات مجموعه', 'group' => 'مدیریت', 'sort_order' => 1010],
    ];

    public function index(): JsonResponse
    {
        $tutorials = Cache::remember('page_tutorials.active', 300, function () {
            return PageTutorial::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->mapWithKeys(fn (PageTutorial $row) => [
                    $row->route_name => $this->formatRow($row),
                ]);
        });

        return response()->json(['tutorials' => $tutorials]);
    }

    public function adminIndex(): JsonResponse
    {
        $saved = PageTutorial::query()->get()->keyBy('route_name');

        $items = collect(self::CATALOG)->map(function (array $item) use ($saved) {
            $row = $saved->get($item['route_name']);

            return [
                'route_name' => $item['route_name'],
                'group' => $item['group'],
                'sort_order' => $item['sort_order'],
                'title' => $row?->title ?? $item['title'],
                'description' => $row?->description,
                'video_url' => $row?->video_url,
                'poster_url' => $row?->poster_url,
                'is_active' => $row?->is_active ?? false,
                'has_record' => $row !== null,
                'updated_at' => $row?->updated_at,
            ];
        });

        return response()->json(['items' => $items]);
    }

    public function adminUpsert(Request $request, string $routeName): JsonResponse
    {
        $catalog = collect(self::CATALOG)->firstWhere('route_name', $routeName);

        if (! $catalog) {
            abort(404, 'صفحه آموزشی یافت نشد.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'poster_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $row = PageTutorial::updateOrCreate(
            ['route_name' => $routeName],
            [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'video_url' => $data['video_url'] ?? null,
                'poster_url' => $data['poster_url'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $catalog['sort_order'],
            ],
        );

        Cache::forget('page_tutorials.active');

        return response()->json([
            'tutorial' => $this->formatRow($row),
            'message' => 'آموزش ذخیره شد.',
        ]);
    }

    protected function formatRow(PageTutorial $row): array
    {
        return [
            'title' => $row->title,
            'description' => $row->description,
            'videoUrl' => $row->video_url,
            'posterUrl' => $row->poster_url,
            'isActive' => $row->is_active,
        ];
    }
}
