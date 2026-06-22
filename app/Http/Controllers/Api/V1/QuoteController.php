<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Quote\QuoteService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected QuoteService $quotes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('invoicing.read');

        $quotes = Quote::query()
            ->with(['contact:id,name', 'deal:id,title', 'issuer:id,name'])
            ->withCount('lineItems')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->deal_id, fn ($q, $id) => $q->where('deal_id', $id))
            ->when($request->contact_id, fn ($q, $id) => $q->where('contact_id', $id))
            ->latest()
            ->paginate(20);

        return response()->json($quotes);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('invoicing.create');

        $data = $request->validate([
            'type' => ['nullable', 'string', 'in:proforma,invoice'],
            'status' => ['nullable', 'string', 'in:draft,sent,accepted,rejected,cancelled'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'deal_id' => ['nullable', 'exists:deals,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.product_id' => ['nullable', 'exists:products,id'],
            'line_items.*.description' => ['nullable', 'string'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $quote = Quote::create([
            'number' => $this->quotes->generateNumber(),
            'type' => $data['type'] ?? 'proforma',
            'status' => $data['status'] ?? 'draft',
            'contact_id' => $data['contact_id'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'discount' => $data['discount'] ?? 0,
            'tax' => $data['tax'] ?? 0,
            'currency' => $data['currency'] ?? 'IRR',
            'valid_until' => $data['valid_until'] ?? null,
            'notes' => $data['notes'] ?? null,
            'issued_by' => $this->crmUser()->id,
        ]);

        $quote = $this->quotes->syncLineItems($quote, $data['line_items']);

        return response()->json(['quote' => $quote], 201);
    }

    public function show(Quote $quote): JsonResponse
    {
        $this->requirePermission('invoicing.read');

        return response()->json([
            'quote' => $quote->load(['lineItems.product', 'contact', 'deal', 'lead', 'issuer:id,name']),
        ]);
    }

    public function update(Request $request, Quote $quote): JsonResponse
    {
        $this->requirePermission('invoicing.update');

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:draft,sent,accepted,rejected,cancelled'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'deal_id' => ['nullable', 'exists:deals,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'line_items' => ['sometimes', 'array', 'min:1'],
            'line_items.*.product_id' => ['nullable', 'exists:products,id'],
            'line_items.*.description' => ['nullable', 'string'],
            'line_items.*.quantity' => ['required_with:line_items', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required_with:line_items', 'numeric', 'min:0'],
            'line_items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lineItems = $data['line_items'] ?? null;
        unset($data['line_items']);

        if (! empty($data)) {
            $quote->update($data);
        }

        if ($lineItems !== null) {
            $quote = $this->quotes->syncLineItems($quote, $lineItems);
        }

        return response()->json([
            'quote' => $quote->fresh(['lineItems.product', 'contact', 'deal', 'lead', 'issuer:id,name']),
        ]);
    }

    public function destroy(Quote $quote): JsonResponse
    {
        $this->requirePermission('invoicing.update');
        $quote->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function send(Quote $quote): JsonResponse
    {
        $this->requirePermission('invoicing.update');

        $quote->update(['status' => 'sent']);

        return response()->json([
            'quote' => $quote->fresh(['lineItems.product', 'contact', 'deal']),
            'message' => 'پیش‌فاکتور ارسال شد.',
        ]);
    }
}
