<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\WebForms\WebFormService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\WebForm;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebFormController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected WebFormService $webForms,
    ) {}

    public function dashboard(): JsonResponse
    {
        $this->requirePermission('web_forms.read');

        return response()->json([
            'forms_count' => WebForm::query()->count(),
            'active_forms_count' => WebForm::query()->where('is_active', true)->count(),
            'submissions_count' => WebForm::query()->sum('submissions_count'),
            'latest_forms' => WebForm::query()
                ->latest('last_submitted_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->requirePermission('web_forms.read');

        $forms = WebForm::query()
            ->withCount('submissions')
            ->latest()
            ->get();

        return response()->json(['forms' => $forms]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('web_forms.create');

        $data = $this->validatedFormData($request);
        $form = $this->webForms->create($data);

        return response()->json(['form' => $form], 201);
    }

    public function show(WebForm $webForm): JsonResponse
    {
        $this->requirePermission('web_forms.read');

        return response()->json(['form' => $webForm->loadCount('submissions')]);
    }

    public function update(Request $request, WebForm $webForm): JsonResponse
    {
        $this->requirePermission('web_forms.manage');

        $data = $this->validatedFormData($request, true);
        $form = $this->webForms->update($webForm, $data);

        return response()->json(['form' => $form]);
    }

    public function destroy(WebForm $webForm): JsonResponse
    {
        $this->requirePermission('web_forms.manage');

        $webForm->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function submissions(Request $request, WebForm $webForm): JsonResponse
    {
        $this->requirePermission('web_forms.read');

        $submissions = $webForm->submissions()
            ->with('lead:id,name,phone,email,company')
            ->latest('submitted_at')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json($submissions);
    }

    public function report(WebForm $webForm): JsonResponse
    {
        $this->requirePermission('web_forms.read');

        $submissions = $webForm->submissions()
            ->with('lead:id,name,phone,email,company')
            ->latest('submitted_at')
            ->get();

        $total = $submissions->count();
        $leadCount = $submissions->whereNotNull('lead_id')->count();
        $fields = collect($webForm->schema['fields'] ?? [])
            ->filter(fn ($field) => ! in_array($field['type'] ?? null, ['heading', 'paragraph'], true))
            ->values();

        $fieldStats = $fields->map(function (array $field) use ($submissions, $total) {
            $key = $field['key'] ?? null;
            $filled = 0;
            $optionCounts = [];

            if (! $key) {
                return null;
            }

            foreach ($submissions as $submission) {
                $payload = $submission->payload ?? [];
                $value = $payload[$key] ?? null;

                if ($this->payloadValueFilled($value, $field['type'] ?? 'text')) {
                    $filled++;
                }

                if (in_array($field['type'] ?? null, ['select', 'multi_select'], true)) {
                    foreach ((array) $value as $selected) {
                        if ($selected === null || $selected === '') {
                            continue;
                        }

                        $optionCounts[(string) $selected] = ($optionCounts[(string) $selected] ?? 0) + 1;
                    }
                }
            }

            $options = collect($field['options'] ?? [])
                ->map(function ($option) use ($optionCounts, $total) {
                    $value = $option['value'] ?? $option['title'] ?? '';
                    $count = $optionCounts[(string) $value] ?? 0;

                    return [
                        'title' => $option['title'] ?? $value,
                        'value' => $value,
                        'count' => $count,
                        'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                    ];
                })
                ->values()
                ->all();

            return [
                'key' => $key,
                'label' => $field['label'] ?? $key,
                'type' => $field['type'] ?? 'text',
                'required' => (bool) ($field['required'] ?? false),
                'filled_count' => $filled,
                'empty_count' => max(0, $total - $filled),
                'completion_percentage' => $total > 0 ? round(($filled / $total) * 100, 1) : 0,
                'options' => $options,
            ];
        })->filter()->values();

        $daily = collect(range(29, 0))->map(function (int $daysAgo) use ($submissions) {
            $date = now()->subDays($daysAgo)->toDateString();

            return [
                'date' => $date,
                'count' => $submissions->filter(fn ($submission) => Carbon::parse($submission->submitted_at)->toDateString() === $date)->count(),
            ];
        })->values();

        return response()->json([
            'summary' => [
                'total_submissions' => $total,
                'lead_count' => $leadCount,
                'conversion_percentage' => $total > 0 ? round(($leadCount / $total) * 100, 1) : 0,
                'last_submitted_at' => $webForm->last_submitted_at,
            ],
            'field_stats' => $fieldStats,
            'daily_submissions' => $daily,
            'recent_submissions' => $submissions->take(5)->values(),
        ]);
    }

    public function publicShow(string $token): JsonResponse
    {
        $form = $this->publicForm($token);

        return response()->json([
            'form' => [
                'name' => $form->name,
                'description' => $form->description,
                'schema' => $form->schema,
                'public_token' => $form->public_token,
                'settings' => [
                    'success_message' => $form->settings['success_message'] ?? null,
                    'redirect_url' => $form->settings['redirect_url'] ?? null,
                    'multi_step' => (bool) ($form->settings['multi_step'] ?? false),
                    'branding' => $form->settings['branding'] ?? [],
                ],
            ],
        ]);
    }

    public function publicSubmit(Request $request, string $token): JsonResponse
    {
        $form = $this->publicForm($token);
        $data = $request->validate([
            'payload' => ['required', 'array'],
        ]);

        $submission = $this->webForms->submit($form, $data['payload'], $request);

        return response()->json([
            'message' => $form->settings['success_message'] ?? 'فرم با موفقیت ثبت شد.',
            'redirect_url' => $form->settings['redirect_url'] ?? null,
            'submission_id' => $submission->id,
            'lead_id' => $submission->lead_id,
        ], 201);
    }

    protected function publicForm(string $token): WebForm
    {
        return WebForm::query()
            ->withoutGlobalScopes()
            ->where('public_token', $token)
            ->where('is_active', true)
            ->firstOrFail();
    }

    protected function payloadValueFilled(mixed $value, string $type): bool
    {
        if ($type === 'checkbox') {
            return $value === true || $value === 1 || $value === '1';
        }

        if (is_array($value)) {
            return count(array_filter($value, fn ($item) => $item !== null && $item !== '')) > 0;
        }

        return $value !== null && trim((string) $value) !== '';
    }

    protected function validatedFormData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'schema' => [$partial ? 'sometimes' : 'required', 'array'],
            'schema.fields' => ['nullable', 'array'],
            'schema.fields.*.id' => ['nullable', 'string', 'max:100'],
            'schema.fields.*.key' => ['nullable', 'string', 'max:100'],
            'schema.fields.*.type' => ['required_with:schema.fields', Rule::in([
                'text',
                'textarea',
                'email',
                'phone',
                'number',
                'select',
                'multi_select',
                'date',
                'checkbox',
                'heading',
                'paragraph',
            ])],
            'schema.fields.*.label' => ['required_with:schema.fields', 'string', 'max:255'],
            'schema.fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'schema.fields.*.required' => ['nullable', 'boolean'],
            'schema.fields.*.options' => ['nullable', 'array'],
            'schema.fields.*.options.*.title' => ['nullable', 'string', 'max:255'],
            'schema.fields.*.options.*.value' => ['nullable', 'string', 'max:255'],
            'schema.fields.*.help_text' => ['nullable', 'string', 'max:500'],
            'settings' => ['nullable', 'array'],
            'settings.create_lead' => ['nullable', 'boolean'],
            'settings.campaign_id' => ['nullable', 'exists:campaigns,id'],
            'settings.marketing_stage_id' => ['nullable', 'exists:pipeline_stages,id'],
            'settings.redirect_url' => ['nullable', 'url', 'max:500'],
            'settings.success_message' => ['nullable', 'string', 'max:500'],
            'settings.multi_step' => ['nullable', 'boolean'],
            'settings.branding' => ['nullable', 'array'],
            'settings.branding.brand_name' => ['nullable', 'string', 'max:100'],
            'settings.branding.headline' => ['nullable', 'string', 'max:255'],
            'settings.branding.subtitle' => ['nullable', 'string', 'max:500'],
            'settings.branding.logo_url' => ['nullable', 'url', 'max:500'],
            'settings.branding.primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.branding.accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.branding.background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.branding.card_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.lead_mapping' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
