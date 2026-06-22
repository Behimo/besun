<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\DailyWorkEntry;
use App\Infrastructure\Persistence\Eloquent\Models\DailyWorkReport;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\Workspace;
use App\Infrastructure\Services\TenantContext;
use App\Models\User;
use Illuminate\Database\Seeder;

class DailyWorkReportSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'rahbar-demo')->first();

        if (! $tenant) {
            $this->command?->warn('مجموعه دمو (rahbar-demo) یافت نشد. ابتدا DemoDataSeeder را اجرا کنید.');

            return;
        }

        $workspace = Workspace::where('tenant_id', $tenant->id)->where('is_default', true)->first();

        if (! $workspace) {
            $this->command?->warn('فضای کاری پیش‌فرض یافت نشد.');

            return;
        }

        app(TenantContext::class)->set($tenant->id, $workspace->id);

        $users = [
            'ali' => User::where('phone', '09121234567')->first(),
            'sara' => User::where('phone', '09129876543')->first(),
            'amir' => User::where('phone', '09125551234')->first(),
        ];
        $reviewer = User::where('phone', '09127654321')->first()
            ?? User::where('phone', '09128916390')->first();

        foreach ($users as $key => $user) {
            if (! $user) {
                $this->command?->warn("کاربر دمو «{$key}» یافت نشد.");

                continue;
            }
        }

        $taskByTitle = fn (string $title) => Task::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('title', $title)
            ->value('id');

        $samples = [
            [
                'user' => $users['ali'],
                'days_ago' => 3,
                'status' => 'submitted',
                'summary' => 'روز پرکاری در پیگیری لیدها و بهینه‌سازی کمپین. سه تماس موفق و یک لید گرم برای دمو فردا.',
                'review' => ['score' => 4, 'feedback' => 'پیگیری لیدها منظم بود. گزارش CTR را دقیق‌تر بنویس.'],
                'entries' => [
                    ['title' => 'تماس پیگیری — لیلا باقری', 'description' => 'هماهنگی زمان دمو و ارسال لینک جلسه', 'minutes' => 45, 'effort_score' => 3, 'task' => 'تماس پیگیری — لیلا باقری (دمو فردا)'],
                    ['title' => 'بررسی لیدهای اینستاگرام', 'description' => '۴ لید جدید — تماس اولیه با ۳ نفر', 'minutes' => 90, 'effort_score' => 4, 'task' => 'بررسی لیدهای بازدید — اینستاگرام'],
                    ['title' => 'گزارش CTR کمپین گوگل', 'description' => 'تحلیل هفتگی و پیشنهاد تغییر متن آگهی', 'minutes' => 60, 'effort_score' => 3, 'task' => 'پیگیری گوگل ادز — بهینه‌سازی CTR'],
                ],
            ],
            [
                'user' => $users['sara'],
                'days_ago' => 3,
                'status' => 'submitted',
                'summary' => 'تمرکز روی پیشنهاد قیمت و جلسات فروش. پیشنهاد اتا ارسال شد.',
                'review' => ['score' => 5, 'feedback' => 'عملکرد عالی — پیشنهاد قیمت حرفه‌ای و جلسه دمو موثر بود.'],
                'entries' => [
                    ['title' => 'تهیه پیشنهاد قیمت — حمید رستمی', 'description' => 'پلن سالانه ۸ صندلی + ماژول گزارش — ۷۲ میلیون', 'minutes' => 120, 'effort_score' => 4, 'task' => 'ارسال پیشنهاد قیمت — حمید رستمی'],
                    ['title' => 'جلسه دمو — شرکت بتا', 'description' => 'نمایش قیف فروش و کانبان — بازخورد مثبت', 'minutes' => 75, 'effort_score' => 3, 'task' => null],
                    ['title' => 'به‌روزرسانی معاملات باخته', 'description' => 'ثبت دلیل از دست رفتن zeta و lambda', 'minutes' => 30, 'effort_score' => 2, 'task' => 'آپدیت CRM — معاملات باخته'],
                ],
            ],
            [
                'user' => $users['amir'],
                'days_ago' => 3,
                'status' => 'submitted',
                'summary' => 'onboarding مشتری جدید و پیگیری تیکت پشتیبانی.',
                'review' => ['score' => 4, 'feedback' => 'onboarding خوب پیش رفت. مستندسازی مراحل را ادامه بده.'],
                'entries' => [
                    ['title' => 'جلسه onboarding — مشتری فلاح', 'description' => 'آموزش تیم ۵ نفره — تنظیم نقش‌ها و دسترسی‌ها', 'minutes' => 150, 'effort_score' => 4, 'task' => 'onboarding مشتری فلاح'],
                    ['title' => 'پیگیری تیکت یکپارچه‌سازی SMS', 'description' => 'هماهنگی با پشتیبانی فنی — ETA دو هفته', 'minutes' => 40, 'effort_score' => 2, 'task' => 'هماهنگی با پشتیبانی — تیکت آلفا'],
                ],
            ],
            [
                'user' => $users['ali'],
                'days_ago' => 2,
                'status' => 'submitted',
                'summary' => 'دمو با لیلا باقری انجام شد. پیگیری VIP و یادآوری شخصی.',
                'review' => ['score' => 5, 'feedback' => 'دمو بسیار خوب بود. ادامه پیگیری VIP را در اولویت بگذار.'],
                'entries' => [
                    ['title' => 'دمو محصول — لیلا باقری', 'description' => 'نمایش ماژول CRM و گزارش کار — علاقه به پلن Enterprise', 'minutes' => 60, 'effort_score' => 4, 'task' => 'تماس پیگیری — لیلا باقری (دمو فردا)'],
                    ['title' => 'تماس پیگیری مشتری VIP', 'description' => 'تأیید تمدید سالانه — ارجاع به قرارداد', 'minutes' => 30, 'effort_score' => 3, 'task' => 'یادآوری پیگیری مشتری VIP'],
                    ['title' => 'پاسخ ایمیل‌های ورودی', 'description' => '۶ ایمیل — ۲ لید جدید ثبت شد', 'minutes' => 45, 'effort_score' => 2, 'task' => null],
                ],
            ],
            [
                'user' => $users['sara'],
                'days_ago' => 2,
                'status' => 'submitted',
                'summary' => 'گزارش عملکرد وبینار آماده شد. پیگیری گلناز احمدی برای امضا.',
                'review' => ['score' => 4, 'feedback' => 'گزارش وبینار کامل بود. پیگیری قرارداد را تا فردا انجام بده.'],
                'entries' => [
                    ['title' => 'گزارش عملکرد کمپین وبینار', 'description' => '۱۵۰ شرکت‌کننده — ۲۱ لید — نرخ تبدیل ۱۸٪', 'minutes' => 90, 'effort_score' => 3, 'task' => 'گزارش عملکرد کمپین وبینار'],
                    ['title' => 'تماس — گلناز احمدی', 'description' => 'آماده امضا — هماهنگی ارسال قرارداد', 'minutes' => 25, 'effort_score' => 3, 'task' => null],
                    ['title' => 'ایمیل تشکر — معرفی مشتری', 'description' => 'ارسال به مینا فرهمند', 'minutes' => 15, 'effort_score' => 1, 'task' => null],
                ],
            ],
            [
                'user' => $users['ali'],
                'days_ago' => 1,
                'status' => 'submitted',
                'summary' => 'روز سبک‌تر — تمرکز روی لیدهای اینستاگرام و آماده‌سازی هفته بعد.',
                'entries' => [
                    ['title' => 'تماس با لیدهای اینستاگرام', 'description' => '۲ تماس موفق — ۱ نوبت دمو رزرو شد', 'minutes' => 70, 'effort_score' => 3, 'task' => 'بررسی لیدهای بازدید — اینستاگرام'],
                    ['title' => 'برنامه‌ریزی هفته آینده', 'description' => 'اولویت‌بندی لیدها در CRM', 'minutes' => 35, 'effort_score' => 2, 'task' => null],
                ],
            ],
            [
                'user' => $users['sara'],
                'days_ago' => 1,
                'status' => 'draft',
                'summary' => 'پیش‌نویس — فردا تکمیل و ارسال می‌شود.',
                'entries' => [
                    ['title' => 'پیگیری پیشنهاد — حمید رستمی', 'description' => 'تماس برای بازخورد پیشنهاد قیمت', 'minutes' => 20, 'effort_score' => 2, 'task' => 'ارسال پیشنهاد قیمت — حمید رستمی'],
                ],
            ],
            [
                'user' => $users['amir'],
                'days_ago' => 1,
                'status' => 'submitted',
                'summary' => 'ادامه onboarding و مستندسازی فرآیند پرداخت.',
                'entries' => [
                    ['title' => 'جلسه دوم onboarding — فلاح', 'description' => 'آموزش گزارش‌ها و داشبورد مدیریتی', 'minutes' => 90, 'effort_score' => 3, 'task' => 'onboarding مشتری فلاح'],
                    ['title' => 'بررسی فاکتورهای معوق', 'description' => '۲ فاکتور — یادآوری ارسال شد', 'minutes' => 40, 'effort_score' => 2, 'task' => null],
                ],
            ],
            [
                'user' => $users['ali'],
                'days_ago' => 0,
                'status' => 'draft',
                'summary' => null,
                'entries' => [
                    ['title' => 'تماس صبحگاهی — پارسا نوری', 'description' => 'علاقه اولیه — ارسال بروشور', 'minutes' => 25, 'effort_score' => 2, 'task' => null],
                ],
            ],
        ];

        $created = 0;

        foreach ($samples as $sample) {
            if (! $sample['user']) {
                continue;
            }

            $reportDate = now()->subDays($sample['days_ago'])->toDateString();
            $totalMinutes = array_sum(array_column($sample['entries'], 'minutes'));

            $report = DailyWorkReport::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $sample['user']->id,
                    'report_date' => $reportDate,
                ],
                [
                    'workspace_id' => $workspace->id,
                    'status' => $sample['status'],
                    'summary' => $sample['summary'],
                    'total_minutes' => $sample['status'] === 'submitted' ? $totalMinutes : 0,
                    'submitted_at' => $sample['status'] === 'submitted'
                        ? now()->subDays($sample['days_ago'])->setTime(17, 30)
                        : null,
                    'manager_score' => isset($sample['review']) ? $sample['review']['score'] : null,
                    'manager_feedback' => $sample['review']['feedback'] ?? null,
                    'reviewed_by' => isset($sample['review']) ? $reviewer?->id : null,
                    'reviewed_at' => isset($sample['review'])
                        ? now()->subDays($sample['days_ago'])->setTime(18, 0)
                        : null,
                ],
            );

            $report->entries()->delete();

            foreach (array_values($sample['entries']) as $index => $entry) {
                DailyWorkEntry::create([
                    'daily_work_report_id' => $report->id,
                    'title' => $entry['title'],
                    'description' => $entry['description'] ?? null,
                    'minutes' => $entry['minutes'],
                    'effort_score' => $entry['effort_score'],
                    'task_id' => isset($entry['task']) && $entry['task']
                        ? $taskByTitle($entry['task'])
                        : null,
                    'sort_order' => $index,
                ]);
            }

            if ($sample['status'] === 'draft') {
                $report->update(['total_minutes' => array_sum(array_column($sample['entries'], 'minutes'))]);
            }

            $created++;
        }

        $this->command?->info("✅ {$created} گزارش کار نمونه ثبت شد.");
        $this->command?->table(
            ['کارمند', 'تاریخ', 'وضعیت', 'آیتم‌ها', 'دقیقه'],
            DailyWorkReport::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->with('user')
                ->orderByDesc('report_date')
                ->get()
                ->map(fn ($r) => [
                    $r->user?->name ?? '—',
                    $r->report_date->format('Y-m-d'),
                    $r->status === 'submitted' ? 'ارسال‌شده' : 'پیش‌نویس',
                    $r->entries()->count(),
                    $r->total_minutes,
                ])
                ->toArray(),
        );
    }
}
