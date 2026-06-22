<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingTransactionController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\CrmHandoffController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DealController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\MarketingLeadController;
use App\Http\Controllers\Api\V1\ModuleCatalogController;
use App\Http\Controllers\Api\V1\TenantChatController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PipelineStageController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TenantAccessController;
use App\Http\Controllers\Api\V1\TenantTeamController;
use App\Http\Controllers\Api\V1\TenantSettingsController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\DailyWorkReportController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TenantSubscriptionController;
use App\Http\Controllers\Api\V1\BiController;
use App\Http\Controllers\Api\V1\PlatformSmsAdminController;
use App\Http\Controllers\Api\V1\PlatformAdminController;
use App\Http\Controllers\Api\V1\PlatformSupportController;
use App\Http\Controllers\Api\V1\PlatformAuthController;
use App\Http\Controllers\Api\V1\PlatformStaffController;
use App\Http\Controllers\Api\V1\PlatformSupportTicketController;
use App\Http\Controllers\Api\V1\SmsController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\TenantSmsSettingsController;
use App\Http\Controllers\Api\V1\WebFormController;
use App\Http\Controllers\Api\V1\ReportsController;
use App\Http\Controllers\Api\V1\SalesTargetController;
use App\Http\Controllers\Api\V1\PageTutorialController;
use App\Http\Controllers\Api\V1\PlatformUserController;
use App\Http\Controllers\Api\V1\UserManagementController;
use App\Http\Controllers\Api\V1\CrmEntityProductController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\WooCommerceConnectionController;
use App\Http\Controllers\Api\V1\WooCommerceBridgeController;
use App\Http\Controllers\Api\V1\WooCommerceWebhookController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\EnsureTenantCoreActive;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('plans', [PlanController::class, 'index']);
    Route::get('modules/catalog', [ModuleCatalogController::class, 'index']);
    Route::post('leads/contact', [MarketingLeadController::class, 'store'])->middleware('throttle:10,1');
    Route::get('forms/{token}', [WebFormController::class, 'publicShow'])->middleware('throttle:60,1');
    Route::post('forms/{token}/submit', [WebFormController::class, 'publicSubmit'])->middleware('throttle:10,1');
    Route::post('integrations/woocommerce/webhook/{token}', [WooCommerceWebhookController::class, 'handle'])->middleware('throttle:120,1');
    Route::post('integrations/woocommerce/bridge/{token}/ping', [WooCommerceBridgeController::class, 'ping'])->middleware('throttle:120,1');
    Route::post('integrations/woocommerce/bridge/{token}/products', [WooCommerceBridgeController::class, 'syncProducts'])->middleware('throttle:120,1');
    Route::post('integrations/woocommerce/bridge/{token}/orders', [WooCommerceBridgeController::class, 'syncOrders'])->middleware('throttle:120,1');
    Route::post('integrations/woocommerce/bridge/{token}/order', [WooCommerceBridgeController::class, 'pushOrder'])->middleware('throttle:120,1');
    Route::post('integrations/woocommerce/bridge/{token}/commands', [WooCommerceBridgeController::class, 'commands'])->middleware('throttle:120,1');
    Route::post('integrations/woocommerce/bridge/{token}/commands/ack', [WooCommerceBridgeController::class, 'ackCommands'])->middleware('throttle:120,1');

    Route::prefix('auth')->group(function () {
        Route::post('otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:10,1');
        Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,1');
        Route::post('platform/login', [PlatformAuthController::class, 'login'])->middleware('throttle:20,1');
    });

    Route::middleware(['auth:sanctum', 'platform.staff'])->group(function () {
        Route::prefix('auth/platform')->group(function () {
            Route::get('me', [PlatformAuthController::class, 'me']);
            Route::post('logout', [PlatformAuthController::class, 'logout']);
        });

        Route::middleware('platform.support')->prefix('platform/support')->group(function () {
            Route::get('dashboard', [PlatformSupportController::class, 'dashboard']);
            Route::get('search', [PlatformSupportController::class, 'search']);
        });

        Route::middleware('platform.operations')->prefix('platform/support')->group(function () {
            Route::get('tickets', [PlatformSupportTicketController::class, 'index']);
            Route::post('tickets', [PlatformSupportTicketController::class, 'store']);
            Route::get('tickets/{ticket}', [PlatformSupportTicketController::class, 'show']);
            Route::patch('tickets/{ticket}', [PlatformSupportTicketController::class, 'update']);
            Route::post('tickets/{ticket}/messages', [PlatformSupportTicketController::class, 'addMessage']);
        });

        Route::middleware('platform.sms.admin')->prefix('platform/sms')->group(function () {
            Route::get('requests', [PlatformSmsAdminController::class, 'requests']);
            Route::get('tenants/{tenant}', [PlatformSmsAdminController::class, 'showTenant']);
            Route::post('tenants/{tenant}/approve', [PlatformSmsAdminController::class, 'approve']);
            Route::post('tenants/{tenant}/reject', [PlatformSmsAdminController::class, 'reject']);
            Route::post('tenants/{tenant}/sync-credit', [PlatformSmsAdminController::class, 'syncCredit']);
        });

        Route::middleware('platform.admin')->prefix('platform')->group(function () {
            Route::get('dashboard', [PlatformAdminController::class, 'dashboard']);
            Route::get('reports', [PlatformAdminController::class, 'reports']);
            Route::get('transactions', [PlatformAdminController::class, 'transactions']);
            Route::get('transactions/export', [PlatformAdminController::class, 'exportTransactions']);
            Route::get('tenants', [PlatformAdminController::class, 'tenants']);
            Route::get('tenants/{tenant}', [PlatformAdminController::class, 'showTenant']);
            Route::patch('tenants/{tenant}/status', [PlatformAdminController::class, 'updateTenantStatus']);
            Route::get('marketing-leads', [PlatformAdminController::class, 'marketingLeads']);
            Route::get('audit-logs', [PlatformAdminController::class, 'auditLogs']);
        });

        Route::middleware('platform.super_admin')->prefix('platform/staff')->group(function () {
            Route::get('/', [PlatformStaffController::class, 'index']);
            Route::post('/', [PlatformStaffController::class, 'store']);
            Route::patch('{staffMember}', [PlatformStaffController::class, 'update']);
        });

        Route::middleware('platform.admin')->prefix('platform/page-tutorials')->group(function () {
            Route::get('/', [PageTutorialController::class, 'adminIndex']);
            Route::put('{routeName}', [PageTutorialController::class, 'adminUpsert']);
        });
    });

    Route::middleware(['auth:sanctum', 'customer', SetTenantContext::class])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });

        Route::get('tenants', [TenantController::class, 'index']);
        Route::post('tenants', [TenantController::class, 'store']);
        Route::post('tenants/exit', [TenantController::class, 'exitShell']);
        Route::post('tenants/{tenant}/switch', [TenantController::class, 'switch']);
        Route::post('tenants/{tenant}/subscription/preview', [TenantSubscriptionController::class, 'preview']);
        Route::post('tenants/{tenant}/subscription/purchase', [TenantSubscriptionController::class, 'purchase']);
        Route::get('billing/transactions', [BillingTransactionController::class, 'index']);

        Route::get('page-tutorials', [PageTutorialController::class, 'index']);

        Route::get('profile/me', [ProfileController::class, 'me']);
        Route::patch('profile/me', [ProfileController::class, 'update']);

        Route::get('invitations', [InvitationController::class, 'index']);
        Route::post('invitations/{invitation}/accept', [InvitationController::class, 'accept']);
        Route::post('invitations/{invitation}/reject', [InvitationController::class, 'reject']);

        Route::middleware(EnsureTenantAccess::class)->group(function () {
            Route::get('workspaces', [WorkspaceController::class, 'index']);
            Route::post('workspaces', [WorkspaceController::class, 'store']);
            Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switch']);

            Route::get('subscription', [SubscriptionController::class, 'show']);
            Route::post('subscription/activate', [SubscriptionController::class, 'activate']);
            Route::post('subscription/modules', [SubscriptionController::class, 'addModules']);
            Route::post('subscription/modules/purchase', [SubscriptionController::class, 'purchaseModules']);
            Route::post('subscription/modules/required/{slug}', [SubscriptionController::class, 'previewModule']);

            Route::get('tenants/{tenant}/invitations', [InvitationController::class, 'tenantIndex']);
            Route::post('tenants/{tenant}/invitations', [InvitationController::class, 'store']);
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy']);

            Route::get('platform/users/search', [PlatformUserController::class, 'search']);
            Route::get('platform/users/{user}', [PlatformUserController::class, 'show']);
            Route::post('platform/users/{user}/reviews', [PlatformUserController::class, 'storeReview']);

            Route::get('users', [UserManagementController::class, 'index']);
            Route::put('users/{user}/role', [UserManagementController::class, 'updateRole']);
            Route::get('users/{user}/access', [UserManagementController::class, 'showAccess']);
            Route::put('users/{user}/access', [UserManagementController::class, 'updateAccess']);

            Route::get('tenant/access/catalog', [TenantAccessController::class, 'catalog']);
            Route::get('tenant/access/roles', [TenantAccessController::class, 'roles']);
            Route::post('tenant/access/roles', [TenantAccessController::class, 'storeRole']);
            Route::patch('tenant/access/roles/{role}', [TenantAccessController::class, 'updateRole']);
            Route::delete('tenant/access/roles/{role}', [TenantAccessController::class, 'destroyRole']);

            Route::get('tenant/teams', [TenantTeamController::class, 'index']);
            Route::post('tenant/teams', [TenantTeamController::class, 'store']);
            Route::patch('tenant/teams/{team}', [TenantTeamController::class, 'update']);
            Route::delete('tenant/teams/{team}', [TenantTeamController::class, 'destroy']);

            Route::get('tenant/settings', [TenantSettingsController::class, 'show']);
            Route::patch('tenant/settings', [TenantSettingsController::class, 'update']);

            Route::get('tenant/sms', [TenantSmsSettingsController::class, 'show']);
            Route::post('tenant/sms/request', [TenantSmsSettingsController::class, 'submitRequest']);
            Route::patch('tenant/sms/settings', [TenantSmsSettingsController::class, 'updateSettings']);

            Route::middleware(EnsureTenantCoreActive::class)->group(function () {
                Route::get('dashboard/stats', [DashboardController::class, 'stats']);
                Route::get('notifications', [NotificationController::class, 'index']);
                Route::post('notifications/broadcast', [NotificationController::class, 'broadcast']);
                Route::get('notifications/broadcasts', [NotificationController::class, 'broadcastHistory']);
                Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
                Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);
                Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
                Route::get('calendar/events', [CalendarController::class, 'index']);
                Route::get('reports', [ReportsController::class, 'index']);
                Route::get('sales-targets', [SalesTargetController::class, 'index']);
                Route::post('sales-targets', [SalesTargetController::class, 'store']);

                Route::middleware('tenant.module:mod-bi')->group(function () {
                    Route::get('bi/dashboard', [BiController::class, 'dashboard']);
                    Route::get('bi/templates', [BiController::class, 'templateList']);
                    Route::get('bi/reports', [BiController::class, 'report']);
                });

                Route::middleware('tenant.module:mod-automation')->group(function () {
                    Route::get('automation/dashboard', [AutomationController::class, 'dashboard']);
                    Route::get('automation/meta', [AutomationController::class, 'meta']);
                    Route::get('automation/rules', [AutomationController::class, 'index']);
                    Route::post('automation/rules', [AutomationController::class, 'store']);
                    Route::get('automation/rules/{automationRule}', [AutomationController::class, 'show']);
                    Route::patch('automation/rules/{automationRule}', [AutomationController::class, 'update']);
                    Route::delete('automation/rules/{automationRule}', [AutomationController::class, 'destroy']);
                    Route::patch('automation/rules/{automationRule}/toggle', [AutomationController::class, 'toggle']);
                    Route::get('automation/runs', [AutomationController::class, 'runs']);
                });

                Route::middleware('tenant.module:mod-sms')->group(function () {
                    Route::get('sms/dashboard', [SmsController::class, 'dashboard']);
                    Route::get('sms/numbers', [SmsController::class, 'numbers']);
                    Route::get('sms/templates', [SmsController::class, 'templates']);
                    Route::post('sms/templates', [SmsController::class, 'storeTemplate']);
                    Route::post('sms/send/preview', [SmsController::class, 'preview']);
                    Route::post('sms/send', [SmsController::class, 'send']);
                    Route::get('sms/messages', [SmsController::class, 'messages']);
                    Route::get('sms/messages/{smsMessage}', [SmsController::class, 'showMessage']);
                    Route::get('sms/credit/packages', [SmsController::class, 'creditPackages']);
                    Route::post('sms/credit/purchase', [SmsController::class, 'purchaseCredit']);
                });

                Route::middleware('tenant.module:mod-web-forms')->group(function () {
                    Route::get('web-forms/dashboard', [WebFormController::class, 'dashboard']);
                    Route::get('web-forms', [WebFormController::class, 'index']);
                    Route::post('web-forms', [WebFormController::class, 'store']);
                    Route::get('web-forms/{webForm}/report', [WebFormController::class, 'report']);
                    Route::get('web-forms/{webForm}', [WebFormController::class, 'show']);
                    Route::patch('web-forms/{webForm}', [WebFormController::class, 'update']);
                    Route::delete('web-forms/{webForm}', [WebFormController::class, 'destroy']);
                    Route::get('web-forms/{webForm}/submissions', [WebFormController::class, 'submissions']);
                });

                Route::apiResource('campaigns', CampaignController::class);

                Route::get('chat/conversations', [TenantChatController::class, 'conversations']);
                Route::get('chat/members', [TenantChatController::class, 'members']);
                Route::get('chat/messages', [TenantChatController::class, 'index']);
                Route::post('chat/messages', [TenantChatController::class, 'store']);
                Route::post('chat/groups', [TenantChatController::class, 'storeGroup']);
                Route::get('chat/groups/{group}', [TenantChatController::class, 'showGroup']);
                Route::patch('chat/groups/{group}', [TenantChatController::class, 'updateGroup']);
                Route::delete('chat/groups/{group}', [TenantChatController::class, 'destroyGroup']);

                Route::get('pipeline-stages', [PipelineStageController::class, 'index']);
                Route::post('pipeline-stages', [PipelineStageController::class, 'store']);
                Route::patch('pipeline-stages/reorder', [PipelineStageController::class, 'reorder']);
                Route::patch('pipeline-stages/{pipelineStage}', [PipelineStageController::class, 'update']);
                Route::delete('pipeline-stages/{pipelineStage}', [PipelineStageController::class, 'destroy']);

                Route::get('contacts/{contact}/profile', [ContactController::class, 'profile']);
                Route::apiResource('contacts', ContactController::class);
                Route::apiResource('leads', LeadController::class);
                Route::post('leads/{lead}/convert', [LeadController::class, 'convert']);
                Route::patch('leads/{lead}/stage', [LeadController::class, 'updateStage']);

                Route::get('deals', [DealController::class, 'index']);
                Route::post('deals', [DealController::class, 'store']);
                Route::get('deals/{deal}', [DealController::class, 'show']);
                Route::put('deals/{deal}', [DealController::class, 'update']);
                Route::patch('deals/{deal}/stage', [DealController::class, 'updateStage']);
                Route::delete('deals/{deal}', [DealController::class, 'destroy']);

                Route::get('handoffs', [CrmHandoffController::class, 'index']);
                Route::post('handoffs', [CrmHandoffController::class, 'store']);
                Route::patch('handoffs/{handoff}/complete', [CrmHandoffController::class, 'complete']);
                Route::post('handoffs/{handoff}/return', [CrmHandoffController::class, 'returnToSender']);

                Route::get('tasks/assignees', [TaskController::class, 'assignees']);
                Route::apiResource('tasks', TaskController::class);
                Route::get('daily-work-reports/today', [DailyWorkReportController::class, 'today']);
                Route::get('daily-work-reports/performance', [DailyWorkReportController::class, 'performance']);
                Route::post('daily-work-reports/{daily_work_report}/submit', [DailyWorkReportController::class, 'submit']);
                Route::post('daily-work-reports/{daily_work_report}/review', [DailyWorkReportController::class, 'review']);
                Route::apiResource('daily-work-reports', DailyWorkReportController::class);
                Route::patch('activities/{activity}', [ActivityController::class, 'update']);
                Route::apiResource('activities', ActivityController::class)->only(['index', 'store', 'destroy']);

                Route::apiResource('products', ProductController::class);
                Route::get('product-categories', [ProductCategoryController::class, 'index']);
                Route::post('product-categories', [ProductCategoryController::class, 'store']);
                Route::patch('product-categories/{productCategory}', [ProductCategoryController::class, 'update']);
                Route::delete('product-categories/{productCategory}', [ProductCategoryController::class, 'destroy']);

                Route::get('leads/{lead}/products', [CrmEntityProductController::class, 'indexLead']);
                Route::put('leads/{lead}/products', [CrmEntityProductController::class, 'syncLead']);
                Route::post('leads/{lead}/products', [CrmEntityProductController::class, 'attachLead']);
                Route::delete('leads/{lead}/products/{productId}', [CrmEntityProductController::class, 'detachLead']);

                Route::get('deals/{deal}/products', [CrmEntityProductController::class, 'indexDeal']);
                Route::put('deals/{deal}/products', [CrmEntityProductController::class, 'syncDeal']);
                Route::post('deals/{deal}/products', [CrmEntityProductController::class, 'attachDeal']);
                Route::delete('deals/{deal}/products/{productId}', [CrmEntityProductController::class, 'detachDeal']);

                Route::apiResource('quotes', QuoteController::class);
                Route::post('quotes/{quote}/send', [QuoteController::class, 'send']);

                Route::middleware('tenant.module:mod-integrations')->group(function () {
                    Route::get('integrations/woocommerce', [WooCommerceConnectionController::class, 'show']);
                    Route::get('integrations/woocommerce/plugin/download', [WooCommerceConnectionController::class, 'downloadPlugin']);
                    Route::post('integrations/woocommerce', [WooCommerceConnectionController::class, 'store']);
                    Route::post('integrations/woocommerce/test', [WooCommerceConnectionController::class, 'test']);
                    Route::post('integrations/woocommerce/sync', [WooCommerceConnectionController::class, 'sync']);
                    Route::post('integrations/woocommerce/sync-orders', [WooCommerceConnectionController::class, 'syncOrders']);
                });
            });
        });
    });
});
