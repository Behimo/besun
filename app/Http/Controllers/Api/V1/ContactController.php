<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Contact\ContactProfileService;
use App\Application\Contact\ContactResolver;
use App\Domain\Shared\Enums\Department;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected ContactProfileService $profiles,
        protected ContactResolver $contactResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('contacts.read');

        $contacts = $this->scopeByDepartment(Contact::query())
            ->withCount(['leads', 'deals'])
            ->with([
                'products' => fn ($q) => $q->select('products.id', 'products.name', 'products.sku', 'products.image_url'),
            ])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($inner) use ($request) {
                    $inner->where('name', 'like', '%'.$request->search.'%')
                        ->orWhere('email', 'like', '%'.$request->search.'%')
                        ->orWhere('phone', 'like', '%'.$request->search.'%');
                });
            })
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 100));

        $contacts->getCollection()->transform(function (Contact $contact) {
            $contact->setAttribute('created_at_jalali', persianDateShort($contact->created_at));

            return $contact;
        });

        return response()->json($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('contacts.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'custom_fields' => ['nullable', 'array'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $data['department'] = Department::Sales->value;
        $contact = $this->contactResolver->findOrCreateFromLeadData($data);

        return response()->json(['contact' => $contact], 201);
    }

    public function show(Contact $contact): JsonResponse
    {
        $this->requirePermission('contacts.read');
        $this->assertCanViewRecord($contact);

        return response()->json(['contact' => $contact->load('assignee:id,name')]);
    }

    public function profile(Request $request, Contact $contact): JsonResponse
    {
        $this->requirePermission('contacts.read');
        $this->assertCanViewRecord($contact);

        return response()->json($this->profiles->build($contact, $request->user()));
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        $this->requirePermission('contacts.update');
        $this->assertCanViewRecord($contact);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'custom_fields' => ['nullable', 'array'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $contact->update($data);

        return response()->json(['contact' => $contact->fresh('assignee:id,name')]);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->requirePermission('contacts.delete');
        $this->assertCanViewRecord($contact);
        $contact->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
