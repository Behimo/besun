<?php

namespace Database\Seeders;

use App\Application\Tenant\TenantProvisioner;
use App\Domain\Shared\Enums\RoleName;
use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Campaign;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\EmployerReview;
use App\Infrastructure\Persistence\Eloquent\Models\Invitation;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Plan;
use App\Infrastructure\Persistence\Eloquent\Models\PlanModule;
use App\Infrastructure\Persistence\Eloquent\Models\Subscription;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantChatGroup;
use App\Infrastructure\Persistence\Eloquent\Models\TenantChatMessage;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfile;
use App\Infrastructure\Persistence\Eloquent\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        $owner = $this->ensureUser([
            'phone' => '09128916390',
            'name' => 'مدیر سیستم',
            'email' => 'owner@rahbar.test',
        ]);

        $tenant = Tenant::where('slug', 'rahbar-demo')->first();

        if (! $tenant) {
            $tenant = app(TenantProvisioner::class)->provision($owner, 'رهبر CRM');
            $tenant->update(['slug' => 'rahbar-demo']);
        } else {
            $tenant->update(['owner_id' => $owner->id, 'name' => 'رهبر CRM', 'status' => 'active']);
            $this->ensureMembership($owner, $tenant, RoleName::Owner->value);
        }

        $workspace = Workspace::firstOrCreate(
            ['tenant_id' => $tenant->id, 'is_default' => true],
            ['name' => 'پیش‌فرض'],
        );

        $this->activateCoreModule($tenant, 10);
        $this->ensurePipelineStages($tenant, $workspace);
        $this->cleanupLegacyPipelineStages($tenant);

        $admin = $this->ensureUser([
            'phone' => '09127654321',
            'name' => 'رضا کریمی',
            'email' => '09127654321@phone.local',
        ]);
        $employeeAli = $this->ensureUser([
            'phone' => '09121234567',
            'name' => 'علی محمدی',
            'email' => '09121234567@phone.local',
        ]);
        $employeeSara = $this->ensureUser([
            'phone' => '09129876543',
            'name' => 'سارا احمدی',
            'email' => '09129876543@phone.local',
        ]);
        $employeeAmir = $this->ensureUser([
            'phone' => '09125551234',
            'name' => 'امیر حسینی',
            'email' => '09125551234@phone.local',
        ]);

        (new TenantPermissionSeeder)->seedForTenant($tenant->id);

        $this->ensureMembership($admin, $tenant, RoleName::SalesManager->value, $owner, 'sales');
        $this->ensureMembership($employeeAli, $tenant, RoleName::MarketingEmployee->value, $owner, 'marketing');
        $this->ensureMembership($employeeSara, $tenant, RoleName::SalesEmployee->value, $owner, 'sales');
        $this->ensureMembership($employeeAmir, $tenant, RoleName::FinanceEmployee->value, $owner, 'finance');

        $this->seedProfiles($employeeSara, $employeeAli, $employeeAmir);
        $this->seedEmployerReviews($tenant, $owner, $employeeSara, $employeeAmir);
        $this->seedPastTenantHistory($employeeAmir, $owner);
        $this->seedInvitation($tenant, $owner);
        $this->seedCrmData($tenant, $workspace, $owner, $admin, $employeeAli, $employeeSara, $employeeAmir);
        $this->seedChat($tenant, $owner, $employeeAli, $employeeSara);
        $this->call(DailyWorkReportSeeder::class);

        $this->enterDemoShell($tenant, $workspace, [$owner, $admin, $employeeAli, $employeeSara, $employeeAmir]);

        $this->command?->newLine();
        $this->command?->info('✅ داده‌های دمو با موفقیت وارد شد.');
        $this->command?->table(
            ['نقش', 'نام', 'موبایل (OTP)', 'توضیح'],
            [
                ['مالک', 'مدیر سیستم', '09128916390', 'مجموعه: رهبر CRM'],
                ['مدیر', 'رضا کریمی', '09127654321', 'دسترسی مدیریتی'],
                ['کارمند', 'علی محمدی', '09121234567', 'فروش و CRM'],
                ['کارمند', 'سارا احمدی', '09129876543', 'پروفایل کامل + نظر کارفرما'],
                ['کارمند', 'امیر حسینی', '09125551234', 'سابقه ۲ مجموعه'],
                ['دعوت‌شده', '—', '09120000099', 'دعوت‌نامه در انتظار (بدون حساب)'],
            ],
        );
        $this->command?->warn('ورود: با OTP و شماره‌های بالا. کد OTP در حالت dev از لاگ یا پاسخ API خوانده می‌شود.');
        $this->command?->warn('⚠️  اگر قبلاً وارد شده‌اید: یک‌بار خارج شوید و دوباره وارد شوید (یا از «مجموعه‌های من» وارد رهبر CRM شوید).');
        $this->command?->newLine();
        $this->command?->info('📊 خلاصه داده CRM (مجموعه رهبر CRM):');
        $this->command?->table(
            ['بخش', 'تعداد'],
            [
                ['کمپین‌ها', Campaign::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count()],
                ['لیدها', Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count()],
                ['مخاطبین', Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count()],
                ['معاملات', Deal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count()],
                ['تسک‌ها', Task::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count()],
                ['فعالیت‌ها', Activity::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count()],
            ],
        );
    }

    protected function ensureUser(array $data): User
    {
        return User::firstOrCreate(
            ['phone' => $data['phone']],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
            ],
        );
    }

    protected function ensureMembership(User $user, Tenant $tenant, string $role, ?User $invitedBy = null, ?string $department = null): void
    {
        $workspace = Workspace::where('tenant_id', $tenant->id)->where('is_default', true)->first();
        $department ??= RoleName::tryFrom($role)?->department()?->value;

        DB::table('tenant_user')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            [
                'joined_at' => now()->subMonths(3),
                'invited_by' => $invitedBy?->id,
                'left_at' => null,
                'department' => $department,
                'permission_overrides' => json_encode(['grant' => [], 'revoke' => []]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        if ($workspace) {
            DB::table('workspace_user')->updateOrInsert(
                ['workspace_id' => $workspace->id, 'user_id' => $user->id],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        setPermissionsTeamId($tenant->id);
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }

    protected function activateCoreModule(Tenant $tenant, int $seatLimit): void
    {
        $coreModule = PlanModule::where('slug', TenantProvisioner::CORE_MODULE_SLUG)->firstOrFail();
        $plan = Plan::where('slug', 'annual')->first() ?? Plan::firstOrFail();

        $subscription = Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addYear(),
                'seat_limit' => $seatLimit,
            ],
        );

        $subscription->modules()->syncWithoutDetaching([
            $coreModule->id => [
                'status' => 'active',
                'subscription_type' => 'annual',
                'expires_at' => now()->addYear(),
                'price_paid' => 0,
                'purchased_at' => now()->subMonth(),
            ],
        ]);

        $tenant->update(['trial_ends_at' => null]);
    }

    protected function ensurePipelineStages(Tenant $tenant, Workspace $workspace): void
    {
        foreach (config('crm_pipeline.sales_stages', []) as $stage) {
            PipelineStage::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $stage['name'],
                    'type' => 'sales',
                ],
                [
                    'workspace_id' => $workspace->id,
                    'sort_order' => $stage['sort_order'],
                    'color' => $stage['color'],
                ],
            );
        }

        foreach (config('crm_pipeline.marketing_stages', []) as $stage) {
            PipelineStage::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $stage['name'],
                    'type' => 'marketing',
                ],
                [
                    'workspace_id' => $workspace->id,
                    'sort_order' => $stage['sort_order'],
                    'color' => $stage['color'],
                ],
            );
        }
    }

    protected function cleanupLegacyPipelineStages(Tenant $tenant): void
    {
        $validSales = collect(config('crm_pipeline.sales_stages', []))->pluck('name')->all();
        $validMarketing = collect(config('crm_pipeline.marketing_stages', []))->pluck('name')->all();

        PipelineStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'sales')
            ->whereNotIn('name', $validSales)
            ->delete();

        PipelineStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'marketing')
            ->whereNotIn('name', $validMarketing)
            ->delete();
    }

    protected function enterDemoShell(Tenant $tenant, Workspace $workspace, array $users): void
    {
        foreach ($users as $user) {
            $user->update([
                'current_tenant_id' => $tenant->id,
                'current_workspace_id' => $workspace->id,
                'in_tenant_shell' => true,
            ]);
        }
    }

    protected function seedProfiles(User $sara, User $ali, User $amir): void
    {
        UserProfile::updateOrCreate(
            ['user_id' => $sara->id],
            [
                'job_title' => 'کارشناس فروش',
                'city' => 'تهران',
                'bio' => '۵ سال سابقه فروش B2B در حوزه نرم‌افزار. مسلط به CRM و مذاکره.',
                'skills' => ['فروش', 'CRM', 'مذاکره', 'فارسی و انگلیسی'],
                'visible_to_owners' => true,
            ],
        );

        UserProfile::updateOrCreate(
            ['user_id' => $ali->id],
            [
                'job_title' => 'کارشناس بازاریابی',
                'city' => 'اصفهان',
                'bio' => 'تخصص در کمپین‌های دیجیتال و تبدیل لید.',
                'skills' => ['بازاریابی', 'گوگل ادز', 'لید جنریشن'],
                'visible_to_owners' => true,
            ],
        );

        UserProfile::updateOrCreate(
            ['user_id' => $amir->id],
            [
                'job_title' => 'پشتیبان فنی',
                'city' => 'شیراز',
                'bio' => 'پشتیبانی مشتری و آموزش کاربران.',
                'skills' => ['پشتیبانی', 'آموزش', 'تیکتینگ'],
                'visible_to_owners' => true,
            ],
        );
    }

    protected function seedEmployerReviews(Tenant $tenant, User $owner, User $sara, User $amir): void
    {
        EmployerReview::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $sara->id],
            [
                'reviewer_id' => $owner->id,
                'rating' => 5,
                'comment' => 'کارمند بسیار متعهد و با انگیزه. نتایج فروش عالی داشت.',
                'role_at_review' => 'employee',
                'is_public' => true,
            ],
        );

        EmployerReview::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $amir->id],
            [
                'reviewer_id' => $owner->id,
                'rating' => 4,
                'comment' => 'پاسخگویی سریع به مشتریان. نیاز به تقویت گزارش‌دهی دارد.',
                'role_at_review' => 'employee',
                'is_public' => true,
            ],
        );
    }

    protected function seedPastTenantHistory(User $amir, User $invitedBy): void
    {
        $pastOwner = $this->ensureUser([
            'phone' => '09128777777',
            'name' => 'حسین مرادی',
            'email' => '09128777777@phone.local',
        ]);

        $pastTenant = Tenant::firstOrCreate(
            ['slug' => 'pars-shop-demo'],
            [
                'name' => 'فروشگاه پارس',
                'owner_id' => $pastOwner->id,
                'status' => 'active',
                'trial_ends_at' => null,
            ],
        );

        $pastWorkspace = Workspace::firstOrCreate(
            ['tenant_id' => $pastTenant->id, 'is_default' => true],
            ['name' => 'پیش‌فرض'],
        );

        $this->ensureMembership($pastOwner, $pastTenant, RoleName::Owner->value);

        DB::table('tenant_user')->updateOrInsert(
            ['tenant_id' => $pastTenant->id, 'user_id' => $amir->id],
            [
                'joined_at' => now()->subMonths(18),
                'left_at' => now()->subMonths(6),
                'invited_by' => $pastOwner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('workspace_user')->updateOrInsert(
            ['workspace_id' => $pastWorkspace->id, 'user_id' => $amir->id],
            ['created_at' => now(), 'updated_at' => now()],
        );

        setPermissionsTeamId($pastTenant->id);
        Role::firstOrCreate([
            'name' => RoleName::Employee->value,
            'guard_name' => 'web',
            'tenant_id' => $pastTenant->id,
        ]);
        if (! $amir->hasRole(RoleName::Employee->value)) {
            $amir->assignRole(RoleName::Employee->value);
        }

        EmployerReview::updateOrCreate(
            ['tenant_id' => $pastTenant->id, 'user_id' => $amir->id],
            [
                'reviewer_id' => $pastOwner->id,
                'rating' => 5,
                'comment' => 'همکاری خوبی داشت. مشتریان از پشتیبانی او راضی بودند.',
                'role_at_review' => 'employee',
                'is_public' => true,
            ],
        );
    }

    protected function seedInvitation(Tenant $tenant, User $owner): void
    {
        Invitation::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'invited_phone' => '09120000099',
                'status' => 'pending',
            ],
            [
                'role' => RoleName::SalesEmployee->value,
                'department' => 'sales',
                'invited_by' => $owner->id,
                'expires_at' => now()->addDays(7),
            ],
        );
    }

    protected function seedCrmData(
        Tenant $tenant,
        Workspace $workspace,
        User $owner,
        User $admin,
        User $employeeAli,
        User $employeeSara,
        User $employeeAmir,
    ): void {
        $salesStages = PipelineStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'sales')
            ->orderBy('sort_order')
            ->get()
            ->keyBy('name');

        $marketingStages = PipelineStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'marketing')
            ->orderBy('sort_order')
            ->get()
            ->keyBy('name');

        $salesStageId = fn (string $name) => $salesStages[$name]?->id ?? $salesStages->first()?->id;
        $marketingStageId = fn (string $name) => $marketingStages[$name]?->id ?? $marketingStages->first()?->id;

        $campaigns = $this->seedCampaigns($tenant, $workspace, $employeeAli, $employeeSara, $admin);
        $contactModels = $this->seedContacts($tenant, $workspace, $employeeSara, $employeeAli, $admin);
        $this->seedLeads($tenant, $workspace, $campaigns, $marketingStageId, $employeeAli, $employeeSara, $admin);
        $this->seedDeals($tenant, $workspace, $salesStageId, $contactModels, $employeeSara, $employeeAli, $owner);
        $this->seedTasks($tenant, $workspace, $employeeAli, $employeeSara, $employeeAmir, $admin);
        $this->seedActivities($tenant, $workspace, $owner, $admin, $employeeAli, $employeeSara, $contactModels);
    }

    protected function seedCampaigns(
        Tenant $tenant,
        Workspace $workspace,
        User $ali,
        User $sara,
        User $admin,
    ): array {
        $definitions = [
            [
                'name' => 'کمپین بهار ۱۴۰۵ — اینستاگرام',
                'description' => 'تبلیغات استوری و ریلز برای جذب SME. CTR هدف: ۲.۵٪',
                'status' => 'active',
                'channel' => 'instagram',
                'budget' => 85000000,
                'starts_at' => now()->subDays(18)->toDateString(),
                'ends_at' => now()->addDays(12)->toDateString(),
                'assigned_to' => $ali->id,
            ],
            [
                'name' => 'گوگل ادز — CRM برای فروش',
                'description' => 'کلیدواژه‌های «CRM فارسی» و «مدیریت مشتری»',
                'status' => 'active',
                'channel' => 'google_ads',
                'budget' => 120000000,
                'starts_at' => now()->subDays(30)->toDateString(),
                'ends_at' => now()->addDays(30)->toDateString(),
                'assigned_to' => $ali->id,
            ],
            [
                'name' => 'وبینار معرفی محصول',
                'description' => 'وبینار زنده با ۱۵۰ ثبت‌نام. تبدیل هدف: ۲۰ لید واجد شرایط',
                'status' => 'completed',
                'channel' => 'webinar',
                'budget' => 35000000,
                'starts_at' => now()->subDays(45)->toDateString(),
                'ends_at' => now()->subDays(40)->toDateString(),
                'assigned_to' => $sara->id,
            ],
            [
                'name' => 'لینکدین — B2B Enterprise',
                'description' => 'هدف‌گیری مدیران فروش شرکت‌های ۵۰+ نفر',
                'status' => 'draft',
                'channel' => 'linkedin',
                'budget' => 60000000,
                'starts_at' => now()->addDays(5)->toDateString(),
                'ends_at' => now()->addDays(35)->toDateString(),
                'assigned_to' => $admin->id,
            ],
            [
                'name' => 'معرفی مشتری (Referral)',
                'description' => 'پاداش ۱۰٪ برای معرفی مشتری جدید',
                'status' => 'active',
                'channel' => 'referral',
                'budget' => 25000000,
                'starts_at' => now()->subDays(60)->toDateString(),
                'ends_at' => now()->addDays(60)->toDateString(),
                'assigned_to' => $sara->id,
            ],
        ];

        $models = [];
        foreach ($definitions as $def) {
            $models[$def['name']] = Campaign::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $def['name']],
                [
                    'workspace_id' => $workspace->id,
                    ...$def,
                ],
            );
        }

        Campaign::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'کمپین بهار ۱۴۰۵')
            ->delete();

        return $models;
    }

    protected function seedContacts(
        Tenant $tenant,
        Workspace $workspace,
        User $sara,
        User $ali,
        User $admin,
    ): array {
        $contacts = [
            ['name' => 'شرکت آلفا فناوری', 'email' => 'info@alpha.ir', 'phone' => '02188776655', 'company' => 'آلفا', 'assign' => $sara->id],
            ['name' => 'مریم رضایی', 'email' => 'maryam@beta.co', 'phone' => '09121112233', 'company' => 'بتا', 'assign' => $sara->id],
            ['name' => 'حامد موسوی', 'email' => 'hamed@gamma.ir', 'phone' => '09123334455', 'company' => 'گاما', 'assign' => $ali->id],
            ['name' => 'فاطمه اکبری', 'email' => 'fateme@delta.io', 'phone' => '09124446677', 'company' => 'دلتا', 'assign' => $sara->id],
            ['name' => 'شرکت اپسیلون', 'email' => 'sales@epsilon.com', 'phone' => '02144556677', 'company' => 'اپسیلون', 'assign' => $ali->id],
            ['name' => 'رضوان پورمحمد', 'email' => 'rezvan@zeta.ir', 'phone' => '09127778899', 'company' => 'زتا', 'assign' => $admin->id],
            ['name' => 'شرکت اتا سیستم', 'email' => 'contact@eta.systems', 'phone' => '02155667788', 'company' => 'اتا', 'assign' => $sara->id],
            ['name' => 'سعید نیکپور', 'email' => 'saeed@theta.app', 'phone' => '09128887766', 'company' => 'تتا', 'assign' => $ali->id],
            ['name' => 'شرکت iota', 'email' => 'hello@iota.biz', 'phone' => '02166778899', 'company' => 'آیوتا', 'assign' => $sara->id],
            ['name' => 'نگین صادقی', 'email' => 'negin@kappa.ir', 'phone' => '09129998877', 'company' => 'کاپا', 'assign' => $admin->id],
            ['name' => 'شرکت lambda', 'email' => 'team@lambda.dev', 'phone' => '02177889900', 'company' => 'لambda', 'assign' => $ali->id],
            ['name' => 'مهدی شریفی', 'email' => 'mahdi@mu.org', 'phone' => '09126665544', 'company' => 'مو', 'assign' => $sara->id],
        ];

        $models = [];
        foreach ($contacts as $contact) {
            $models[] = Contact::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $contact['email']],
                [
                    'workspace_id' => $workspace->id,
                    'name' => $contact['name'],
                    'phone' => $contact['phone'],
                    'company' => $contact['company'],
                    'assigned_to' => $contact['assign'],
                    'tags' => ['دمو', 'B2B'],
                ],
            );
        }

        return $models;
    }

    protected function seedLeads(
        Tenant $tenant,
        Workspace $workspace,
        array $campaigns,
        callable $marketingStageId,
        User $ali,
        User $sara,
        User $admin,
    ): void {
        $campInstagram = $campaigns['کمپین بهار ۱۴۰۵ — اینستاگرام']->id;
        $campGoogle = $campaigns['گوگل ادز — CRM برای فروش']->id;
        $campWebinar = $campaigns['وبینار معرفی محصول']->id;
        $campReferral = $campaigns['معرفی مشتری (Referral)']->id;

        $leads = [
            // بازدید
            ['phone' => '09124000001', 'name' => 'پارسا نوری', 'company' => 'استودیو طراحی پارسا', 'job_title' => 'مدیرعامل', 'city' => 'تهران', 'stage' => 'بازدید', 'score' => 25, 'source' => 'social', 'campaign_id' => $campInstagram, 'assign' => $ali->id, 'notes' => 'از ریلز اینستاگرام — علاقه اولیه'],
            ['phone' => '09124000002', 'name' => 'آرمان داوودی', 'company' => 'فروشگاه آنلاین آرمان', 'job_title' => 'مدیر بازاریابی', 'city' => 'کرج', 'stage' => 'بازدید', 'score' => 20, 'source' => 'campaign', 'campaign_id' => $campGoogle, 'assign' => $ali->id, 'notes' => 'کلیک گوگل ادز — صفحه فرود'],
            ['phone' => '09124000003', 'name' => 'سپیده رحیمی', 'company' => 'کلینیک سپیده', 'job_title' => 'منشی', 'city' => 'شیراز', 'stage' => 'بازدید', 'score' => 15, 'source' => 'website', 'campaign_id' => $campInstagram, 'assign' => $sara->id, 'notes' => 'فرم تماس — نیاز به پیگیری'],
            ['phone' => '09124000004', 'name' => 'بهرام قاسمی', 'company' => 'آژانس مسافرتی سفر', 'job_title' => 'کارشناس فروش', 'city' => 'مشهد', 'stage' => 'بازدید', 'score' => 30, 'source' => 'inbound', 'campaign_id' => null, 'assign' => $admin->id, 'notes' => 'تماس ورودی از وب‌سایت'],

            // علاقه‌مند
            ['phone' => '09124000005', 'name' => 'نیلوفر کاظمی', 'company' => 'اپسیلون', 'job_title' => 'مدیر فروش', 'city' => 'تهران', 'stage' => 'علاقه‌مند', 'score' => 50, 'source' => 'campaign', 'campaign_id' => $campGoogle, 'assign' => $ali->id, 'notes' => 'دانلود بروشور — ۲ تماس انجام شد'],
            ['phone' => '09124000006', 'name' => 'کاوه میرزایی', 'company' => 'تولیدی کاوه', 'job_title' => 'مدیر عامل', 'city' => 'اصفهان', 'stage' => 'علاقه‌مند', 'score' => 45, 'source' => 'webinar', 'campaign_id' => $campWebinar, 'assign' => $sara->id, 'notes' => 'شرکت در وبینار — پرسش Q&A'],
            ['phone' => '09124000007', 'name' => 'مینا فرهمند', 'company' => 'آموزشگاه مینا', 'job_title' => 'مدیر', 'city' => 'تبریز', 'stage' => 'علاقه‌مند', 'score' => 55, 'source' => 'referral', 'campaign_id' => $campReferral, 'assign' => $sara->id, 'notes' => 'معرفی مشتری آلفا'],
            ['phone' => '09124000008', 'name' => 'پیمان سلطانی', 'company' => 'پیمانکاری راه', 'job_title' => 'مدیر پروژه', 'city' => 'اهواز', 'stage' => 'علاقه‌مند', 'score' => 48, 'source' => 'social', 'campaign_id' => $campInstagram, 'assign' => $ali->id, 'notes' => 'دایرکت اینستا — درخواست قیمت'],

            // واجد شرایط
            ['phone' => '09124000009', 'name' => 'کامران جلیلی', 'company' => 'زتا', 'job_title' => 'COO', 'city' => 'تهران', 'stage' => 'واجد شرایط', 'score' => 72, 'source' => 'campaign', 'campaign_id' => $campGoogle, 'assign' => $sara->id, 'notes' => 'بودجه تأیید شده — ۵ تا ۱۰ کاربر'],
            ['phone' => '09124000010', 'name' => 'الهام توکلی', 'company' => 'هلدینگ توکلی', 'job_title' => 'مدیر IT', 'city' => 'تهران', 'stage' => 'واجد شرایط', 'score' => 78, 'source' => 'webinar', 'campaign_id' => $campWebinar, 'assign' => $admin->id, 'notes' => 'نیاز CRM + ماژول بازاریابی'],
            ['phone' => '09124000011', 'name' => 'فرزاد یزدانی', 'company' => 'یزدانی تجارت', 'job_title' => 'مدیر فروش', 'city' => 'یزد', 'stage' => 'واجد شرایط', 'score' => 70, 'source' => 'referral', 'campaign_id' => $campReferral, 'assign' => $ali->id, 'notes' => 'معرفی مستقیم — آماده جلسه'],
            ['phone' => '09124000012', 'name' => 'شیرین عباسی', 'company' => 'عباسی پخش', 'job_title' => 'مالک', 'city' => 'رشت', 'stage' => 'واجد شرایط', 'score' => 68, 'source' => 'inbound', 'campaign_id' => null, 'assign' => $sara->id, 'notes' => 'تیم ۸ نفره — فروش B2B'],

            // آماده فروش
            ['phone' => '09124000013', 'name' => 'لیلا باقری', 'company' => 'اتا', 'job_title' => 'مدیرعامل', 'city' => 'تهران', 'stage' => 'آماده فروش', 'score' => 92, 'source' => 'campaign', 'campaign_id' => $campGoogle, 'assign' => $ali->id, 'notes' => 'دمو فردا — تصمیم‌گیرنده نهایی'],
            ['phone' => '09124000014', 'name' => 'حمید رستمی', 'company' => 'رستمی صنعت', 'job_title' => 'مدیر', 'city' => 'کرمان', 'stage' => 'آماده فروش', 'score' => 88, 'source' => 'webinar', 'campaign_id' => $campWebinar, 'assign' => $sara->id, 'notes' => 'پیشنهاد قیمت ارسال شد — منتظر امضا'],
            ['phone' => '09124000015', 'name' => 'گلناز احمدی', 'company' => 'احمدی لجستیک', 'job_title' => 'مدیر عملیات', 'city' => 'تهران', 'stage' => 'آماده فروش', 'score' => 95, 'source' => 'referral', 'campaign_id' => $campReferral, 'assign' => $admin->id, 'notes' => 'اولویت بالا — رقیب: Salesforce'],
            ['phone' => '09124000016', 'name' => 'دانیال کریمی', 'company' => 'کریمی دیجیتال', 'job_title' => 'بنیان‌گذار', 'city' => 'قم', 'stage' => 'آماده فروش', 'score' => 90, 'source' => 'social', 'campaign_id' => $campInstagram, 'assign' => $ali->id, 'notes' => 'آماده پرداخت — پلن سالانه'],

            // نامرتبط
            ['phone' => '09124000017', 'name' => 'رضا منصوری', 'company' => 'منصوری شخصی', 'job_title' => 'فریلنسر', 'city' => 'بندرعباس', 'stage' => 'نامرتبط', 'score' => 5, 'source' => 'other', 'campaign_id' => $campInstagram, 'assign' => $ali->id, 'notes' => 'بودجه ندارد — آرشیو'],
            ['phone' => '09124000018', 'name' => 'زهرا محمودی', 'company' => '—', 'job_title' => 'دانشجو', 'city' => 'همدان', 'stage' => 'نامرتبط', 'score' => 8, 'source' => 'website', 'campaign_id' => null, 'assign' => $sara->id, 'notes' => 'فقط سوال عمومی'],
            ['phone' => '09124000019', 'name' => 'مسعود حیدری', 'company' => 'حیدری', 'job_title' => '—', 'city' => 'اراک', 'stage' => 'نامرتبط', 'score' => 10, 'source' => 'campaign', 'campaign_id' => $campGoogle, 'assign' => $admin->id, 'notes' => 'کلیک اشتباهی — خارج از ICP'],

            // تبدیل‌شده (برای آمار داشبورد)
            ['phone' => '09124000020', 'name' => 'مجید فلاح', 'company' => 'فلاح بازرگانی', 'job_title' => 'مدیر', 'city' => 'تهران', 'stage' => 'آماده فروش', 'score' => 100, 'source' => 'referral', 'campaign_id' => $campReferral, 'assign' => $sara->id, 'notes' => 'تبدیل به معامله — قرارداد امضا شد', 'status' => 'converted'],
            ['phone' => '09124000021', 'name' => 'نسرین جعفری', 'company' => 'جعفری مد', 'job_title' => 'مدیر فروش', 'city' => 'تهران', 'stage' => 'آماده فروش', 'score' => 100, 'source' => 'webinar', 'campaign_id' => $campWebinar, 'assign' => $ali->id, 'notes' => 'تبدیل موفق — onboarding انجام شد', 'status' => 'converted'],
        ];

        foreach ($leads as $lead) {
            Lead::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'phone' => $lead['phone']],
                [
                    'workspace_id' => $workspace->id,
                    'campaign_id' => $lead['campaign_id'],
                    'marketing_stage_id' => $marketingStageId($lead['stage']),
                    'name' => $lead['name'],
                    'email' => strtolower(str_replace(' ', '.', $lead['name'])).'@demo.local',
                    'company' => $lead['company'],
                    'job_title' => $lead['job_title'],
                    'city' => $lead['city'],
                    'score' => $lead['score'],
                    'status' => $lead['status'] ?? 'new',
                    'source' => $lead['source'],
                    'assigned_to' => $lead['assign'],
                    'notes' => $lead['notes'],
                ],
            );
        }

        // حذف لیدهای قدیمی seeder
        Lead::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('phone', ['09124445566', '09126667788', '09128889900', '09120001122'])
            ->delete();
    }

    protected function seedDeals(
        Tenant $tenant,
        Workspace $workspace,
        callable $salesStageId,
        array $contactModels,
        User $sara,
        User $ali,
        User $owner,
    ): void {
        Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('title', [
                'قرارداد آلفا — پلن سالانه',
                'فروش بتا — ۵ صندلی',
                'گاما — ماژول CRM',
                'تمدید مشتری VIP',
            ])
            ->delete();

        $deals = [
            ['title' => 'آلفا — پلن سالانه ۱۰ صندلی', 'amount' => 95000000, 'stage' => 'مذاکره', 'contact' => 0, 'assign' => $sara->id, 'days' => 7],
            ['title' => 'بتا — تمدید + ۳ صندلی', 'amount' => 42000000, 'stage' => 'مذاکره', 'contact' => 1, 'assign' => $sara->id, 'days' => 10],
            ['title' => 'گاما — ماژول CRM پایه', 'amount' => 18000000, 'stage' => 'سرنخ', 'contact' => 2, 'assign' => $ali->id, 'days' => 21],
            ['title' => 'دلتا — پلن ۶ ماهه', 'amount' => 35000000, 'stage' => 'سرنخ', 'contact' => 3, 'assign' => $ali->id, 'days' => 30],
            ['title' => 'اپسیلون — Enterprise', 'amount' => 185000000, 'stage' => 'سرنخ', 'contact' => 4, 'assign' => $sara->id, 'days' => 45],
            ['title' => 'اتا — قرارداد فوری', 'amount' => 72000000, 'stage' => 'پیشنهاد', 'contact' => 6, 'assign' => $sara->id, 'days' => 5],
            ['title' => 'تتا — ۵ صندلی + آموزش', 'amount' => 48000000, 'stage' => 'پیشنهاد', 'contact' => 7, 'assign' => $ali->id, 'days' => 8],
            ['title' => 'کاپا — ماژول بازاریابی', 'amount' => 28000000, 'stage' => 'پیشنهاد', 'contact' => 9, 'assign' => $sara->id, 'days' => 12],
            ['title' => 'VIP — تمدید سالانه', 'amount' => 125000000, 'stage' => 'برنده', 'contact' => 0, 'assign' => $owner->id, 'days' => -3],
            ['title' => 'فلاح — onboarding', 'amount' => 55000000, 'stage' => 'برنده', 'contact' => 11, 'assign' => $sara->id, 'days' => -7],
            ['title' => 'جعفری — پلن ماهانه', 'amount' => 12000000, 'stage' => 'برنده', 'contact' => 10, 'assign' => $ali->id, 'days' => -14],
            ['title' => 'زeta — مناقصه از دست رفت', 'amount' => 65000000, 'stage' => 'باخته', 'contact' => 5, 'assign' => $ali->id, 'days' => -5],
            ['title' => 'lambda — قیمت بالا', 'amount' => 22000000, 'stage' => 'باخته', 'contact' => 10, 'assign' => $sara->id, 'days' => -10],
        ];

        foreach ($deals as $deal) {
            Deal::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'title' => $deal['title']],
                [
                    'workspace_id' => $workspace->id,
                    'pipeline_stage_id' => $salesStageId($deal['stage']),
                    'contact_id' => $contactModels[$deal['contact']]->id ?? null,
                    'amount' => $deal['amount'],
                    'currency' => 'IRR',
                    'assigned_to' => $deal['assign'],
                    'expected_close_date' => now()->addDays($deal['days'])->toDateString(),
                    'notes' => 'معامله دمو — مناسب پرزنت '.$deal['stage'],
                ],
            );
        }
    }

    protected function seedTasks(
        Tenant $tenant,
        Workspace $workspace,
        User $ali,
        User $sara,
        User $amir,
        User $admin,
    ): void {
        $tasks = [
            ['title' => 'تماس پیگیری — لیلا باقری (دمو فردا)', 'desc' => 'لید آماده فروش — هماهنگی دمو محصول ساعت ۱۰', 'status' => 'pending', 'priority' => 'high', 'days' => 1, 'assign' => $ali->id, 'by' => $admin->id],
            ['title' => 'ارسال پیشنهاد قیمت — حمید رستمی', 'desc' => 'پلن سالانه ۸ صندلی + ماژول گزارش', 'status' => 'in_progress', 'priority' => 'high', 'days' => 2, 'assign' => $sara->id, 'by' => $admin->id],
            ['title' => 'پیگیری گوگل ادز — بهینه‌سازی CTR', 'desc' => 'بررسی گزارش هفتگی کمپین', 'status' => 'in_progress', 'priority' => 'medium', 'days' => 3, 'assign' => $ali->id, 'by' => $admin->id],
            ['title' => 'آماده‌سازی اسلاید پرزنت Enterprise', 'desc' => 'برای جلسه الهام توکلی', 'status' => 'pending', 'priority' => 'medium', 'days' => 4, 'assign' => $admin->id, 'by' => $admin->id],
            ['title' => 'onboarding مشتری فلاح', 'desc' => 'آموزش تیم ۵ نفره — هفته اول', 'status' => 'in_progress', 'priority' => 'high', 'days' => 5, 'assign' => $amir->id, 'by' => $admin->id],
            ['title' => 'بررسی لیدهای بازدید — اینستاگرام', 'desc' => '۴ لید جدید — تماس اولیه', 'status' => 'pending', 'priority' => 'medium', 'days' => 1, 'assign' => $ali->id, 'by' => $sara->id],
            ['title' => 'گزارش عملکرد کمپین وبینار', 'desc' => 'ارسال به مدیر — نرخ تبدیل ۱۸٪', 'status' => 'completed', 'priority' => 'low', 'days' => -2, 'assign' => $sara->id, 'by' => $admin->id],
            ['title' => 'هماهنگی با پشتیبانی — تیکت آلفا', 'desc' => 'پیگیری درخواست یکپارچه‌سازی SMS', 'status' => 'pending', 'priority' => 'medium', 'days' => -1, 'assign' => $amir->id, 'by' => $admin->id],
            ['title' => 'جلسه هفتگی تیم فروش', 'desc' => 'مرور قیف و اهداف هفته', 'status' => 'pending', 'priority' => 'high', 'days' => 0, 'assign' => $admin->id, 'by' => $admin->id],
            ['title' => 'آپدیت CRM — معاملات باخته', 'desc' => 'ثبت دلیل از دست رفت zeta و lambda', 'status' => 'completed', 'priority' => 'low', 'days' => -3, 'assign' => $sara->id, 'by' => $admin->id],
            ['title' => 'یادآوری پیگیری مشتری VIP', 'desc' => 'تسک شخصی', 'status' => 'pending', 'priority' => 'medium', 'days' => 2, 'assign' => $ali->id, 'by' => $ali->id],
        ];

        foreach ($tasks as $task) {
            $dueAt = now()->addDays($task['days']);
            $completedAt = $task['status'] === 'completed' ? $dueAt->copy()->subDay() : null;

            Task::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'title' => $task['title']],
                [
                    'workspace_id' => $workspace->id,
                    'description' => $task['desc'],
                    'status' => $task['status'],
                    'priority' => $task['priority'],
                    'due_at' => $dueAt,
                    'completed_at' => $completedAt,
                    'assignee_id' => $task['assign'],
                    'created_by' => $task['by'],
                    'assigned_by' => $task['by'] !== $task['assign'] ? $task['by'] : null,
                ],
            );
        }
    }

    protected function seedActivities(
        Tenant $tenant,
        Workspace $workspace,
        User $owner,
        User $admin,
        User $ali,
        User $sara,
        array $contacts,
    ): void {
        $activities = [
            ['type' => 'call', 'subject' => 'تماس با لیلا باقری', 'body' => 'دمو فردا ساعت ۱۰ تأیید شد.', 'user' => $ali->id, 'hours' => 2],
            ['type' => 'email', 'subject' => 'ارسال پیشنهاد قیمت — اتا', 'body' => 'پیشنهاد ۷۲ میلیون تومان ارسال شد.', 'user' => $sara->id, 'hours' => 5],
            ['type' => 'meeting', 'subject' => 'جلسه با کامران جلیلی', 'body' => 'نیازمندی‌ها: CRM + ۸ کاربر.', 'user' => $sara->id, 'hours' => 8],
            ['type' => 'note', 'subject' => 'یادداشت — کمپین اینستاگرام', 'body' => 'CTR به ۲.۱٪ رسید — ۱۲ لید جدید.', 'user' => $ali->id, 'hours' => 12],
            ['type' => 'call', 'subject' => 'پیگیری VIP', 'body' => 'تمدید سالانه تأیید — ۱۲۵M', 'user' => $owner->id, 'hours' => 24],
            ['type' => 'email', 'subject' => 'خوش‌آمدگویی — فلاح', 'body' => 'ایمیل onboarding ارسال شد.', 'user' => $sara->id, 'hours' => 30],
            ['type' => 'meeting', 'subject' => 'وبینر محصول', 'body' => '۱۵۰ شرکت‌کننده — ۲۱ لید.', 'user' => $ali->id, 'hours' => 48],
            ['type' => 'call', 'subject' => 'تماس — پارسا نوری', 'body' => 'علاقه اولیه — ارسال بروشور.', 'user' => $ali->id, 'hours' => 3],
            ['type' => 'note', 'subject' => 'از دست رفت — zeta', 'body' => 'رقیب ۱۵٪ ارزان‌تر پیشنهاد داد.', 'user' => $ali->id, 'hours' => 72],
            ['type' => 'call', 'subject' => 'تماس — گلناز احمدی', 'body' => 'آماده امضا — پیگیری فردا.', 'user' => $admin->id, 'hours' => 6],
            ['type' => 'email', 'subject' => 'معرفی — مینا فرهمند', 'body' => 'ایمیل تشکر از معرفی مشتری.', 'user' => $sara->id, 'hours' => 15],
            ['type' => 'meeting', 'subject' => 'دمو محصول — بتا', 'body' => 'نمایش قیف فروش و کانبان.', 'user' => $sara->id, 'hours' => 36],
        ];

        foreach ($activities as $i => $act) {
            Activity::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'subject' => $act['subject'],
                ],
                [
                    'workspace_id' => $workspace->id,
                    'user_id' => $act['user'],
                    'type' => $act['type'],
                    'body' => $act['body'],
                    'happened_at' => now()->subHours($act['hours']),
                    'related_type' => isset($contacts[$i % count($contacts)]) ? Contact::class : null,
                    'related_id' => isset($contacts[$i % count($contacts)]) ? $contacts[$i % count($contacts)]->id : null,
                ],
            );
        }
    }

    protected function seedChat(Tenant $tenant, User $owner, User $ali, User $sara): void
    {
        $groupSales = TenantChatGroup::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'تیم فروش'],
            ['created_by' => $owner->id],
        );

        $groupMarketing = TenantChatGroup::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'تیم بازاریابی'],
            ['created_by' => $owner->id],
        );

        foreach ([$owner, $ali, $sara] as $member) {
            $groupSales->members()->syncWithoutDetaching([$member->id => ['joined_at' => now()]]);
            $groupMarketing->members()->syncWithoutDetaching([$member->id => ['joined_at' => now()]]);
        }

        $messages = [
            ['user_id' => $owner->id, 'body' => 'سلام تیم 👋 هدف این هفته: تبدیل ۳ لید «آماده فروش» به معامله', 'group_id' => $groupSales->id],
            ['user_id' => $ali->id, 'body' => 'لیلا باقری و گلناز احمدی اولویت هستند — دموها فردا', 'group_id' => $groupSales->id],
            ['user_id' => $sara->id, 'body' => 'پیشنهاد اتا و تتا آماده است ✅', 'group_id' => $groupSales->id],
            ['user_id' => $owner->id, 'body' => 'عالیه. گزارش قیف را جمعه بفرستید.', 'group_id' => $groupSales->id],
            ['user_id' => $ali->id, 'body' => 'CTR اینستا به ۲.۱٪ رسید — ۴ لید جدید در «بازدید»', 'group_id' => $groupMarketing->id],
            ['user_id' => $sara->id, 'body' => 'وبینار ۱۸٪ تبدیل داشت — کمپین بعدی را draft کردم', 'group_id' => $groupMarketing->id],
            ['user_id' => $sara->id, 'body' => 'مدیر، پیشنهاد VIP را تأیید کردید؟', 'group_id' => null, 'recipient_id' => $owner->id],
            ['user_id' => $owner->id, 'body' => 'بله — onboarding را امیر پیگیری کند', 'group_id' => null, 'recipient_id' => $sara->id],
        ];

        foreach ($messages as $index => $msg) {
            TenantChatMessage::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $msg['user_id'],
                    'body' => $msg['body'],
                ],
                [
                    'group_id' => $msg['group_id'] ?? null,
                    'recipient_id' => $msg['recipient_id'] ?? null,
                    'created_at' => now()->subHours(count($messages) - $index),
                    'updated_at' => now()->subHours(count($messages) - $index),
                ],
            );
        }
    }
}
