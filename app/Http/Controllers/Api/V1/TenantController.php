<?php



namespace App\Http\Controllers\Api\V1;



use App\Application\Tenant\CreateTenantUseCase;

use App\Application\Tenant\TenantProvisioner;

use App\Http\Controllers\Controller;

use App\Infrastructure\Persistence\Eloquent\Models\Tenant;

use App\Infrastructure\Services\AuthPayloadService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use RuntimeException;



class TenantController extends Controller

{

    public function __construct(

        protected CreateTenantUseCase $createTenant,

        protected TenantProvisioner $tenantProvisioner,

        protected AuthPayloadService $authPayload,

    ) {}



    public function index(Request $request): JsonResponse

    {

        $tenants = $request->user()

            ->tenants()

            ->with(['subscription.plan', 'subscription.modules'])

            ->orderBy('name')

            ->get()

            ->map(fn (Tenant $tenant) => $this->authPayload->formatTenant($tenant));



        return response()->json(['tenants' => $tenants]);

    }



    public function store(Request $request): JsonResponse

    {

        $data = $request->validate([

            'name' => ['required', 'string', 'max:255'],

        ]);



        try {

            $coreModule = $this->tenantProvisioner->ensureCoreModuleCatalog();

        } catch (RuntimeException $e) {

            return response()->json(['message' => $e->getMessage()], 503);

        }



        $tenant = $this->createTenant->execute($request->user(), $data['name']);



        return response()->json([

            'tenant' => $this->authPayload->formatTenant($tenant->fresh(['subscription.plan', 'subscription.modules'])),

            'core_module' => $this->tenantProvisioner->formatCoreModuleSummary($coreModule),

            'redirect' => 'apps-account-modules',

            'open_purchase_for_tenant_id' => $tenant->id,

            ...$this->authPayload->payload($request->user()->fresh()),

        ], 201);

    }



    public function switch(Request $request, Tenant $tenant): JsonResponse

    {

        if (! $request->user()->belongsToTenant($tenant->id)) {

            abort(403);

        }



        $workspace = $tenant->workspaces()->where('is_default', true)->first()

            ?? $tenant->workspaces()->first();



        $request->user()->update([

            'current_tenant_id' => $tenant->id,

            'current_workspace_id' => $workspace?->id,

            'in_tenant_shell' => true,

        ]);



        $payload = $this->authPayload->payload($request->user()->fresh());



        return response()->json([

            ...$payload,

            'redirect' => $tenant->hasActiveCoreModule() ? 'dashboards-crm' : 'apps-tenant-modules',

        ]);

    }



    public function exitShell(Request $request): JsonResponse

    {

        $request->user()->update([

            'in_tenant_shell' => false,

        ]);



        return response()->json($this->authPayload->payload($request->user()->fresh()));

    }

}

