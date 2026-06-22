<?php

namespace App\Application\WebForms;

use App\Application\Automation\AutomationDispatcher;
use App\Application\Contact\ContactResolver;
use App\Application\Lead\LeadResolver;
use App\Application\Pipeline\PipelineTransitionLogger;
use App\Domain\Shared\Enums\Department;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\WebForm;
use App\Infrastructure\Persistence\Eloquent\Models\WebFormSubmission;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebFormService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected ContactResolver $contactResolver,
        protected LeadResolver $leadResolver,
        protected PipelineTransitionLogger $transitions,
        protected AutomationDispatcher $automation,
    ) {}

    public function create(array $data): WebForm
    {
        $data['schema'] = $this->normalizeSchema($data['schema'] ?? []);
        $data['settings'] = $this->normalizeSettings($data['settings'] ?? []);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']);

        return WebForm::create($data)->fresh();
    }

    public function update(WebForm $form, array $data): WebForm
    {
        if (array_key_exists('schema', $data)) {
            $data['schema'] = $this->normalizeSchema($data['schema'] ?? []);
        }

        if (array_key_exists('settings', $data)) {
            $data['settings'] = $this->normalizeSettings($data['settings'] ?? []);
        }

        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name'] ?? $form->slug, $form->id);
        }

        $form->update($data);

        return $form->fresh();
    }

    public function submit(WebForm $form, array $payload, Request $request): WebFormSubmission
    {
        if (! $form->is_active) {
            abort(404);
        }

        $tenant = Tenant::findOrFail($form->tenant_id);

        if (! $tenant->hasModule('mod-web-forms')) {
            abort(404);
        }

        $this->tenantContext->set($form->tenant_id, $form->workspace_id);
        $payload = $this->validatePayload($form, $payload);

        return DB::transaction(function () use ($form, $payload, $request) {
            $lead = null;

            if (Arr::get($form->settings, 'create_lead', true)) {
                $lead = $this->createLeadFromSubmission($form, $payload);
            }

            $submission = WebFormSubmission::create([
                'tenant_id' => $form->tenant_id,
                'workspace_id' => $form->workspace_id,
                'web_form_id' => $form->id,
                'lead_id' => $lead?->id,
                'payload' => $payload,
                'status' => $lead ? 'lead_created' : 'received',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'submitted_at' => now(),
            ]);

            $form->forceFill([
                'submissions_count' => $form->submissions_count + 1,
                'last_submitted_at' => now(),
            ])->save();

            return $submission->fresh(['lead']);
        });
    }

    public function validatePayload(WebForm $form, array $payload): array
    {
        $fields = collect($form->schema['fields'] ?? [])
            ->filter(fn ($field) => ! in_array($field['type'] ?? null, ['heading', 'paragraph'], true));

        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            $key = $field['key'] ?? null;

            if (! $key) {
                continue;
            }

            $fieldRules = [Arr::get($field, 'required') ? 'required' : 'nullable'];

            $fieldRules[] = match ($field['type'] ?? 'text') {
                'email' => 'email',
                'number' => 'numeric',
                'checkbox' => 'boolean',
                'multi_select' => 'array',
                'date' => 'date',
                default => 'string',
            };

            if (in_array($field['type'] ?? null, ['select', 'multi_select'], true)) {
                $allowed = collect($field['options'] ?? [])->pluck('value')->filter()->values()->all();

                if ($allowed) {
                    $fieldRules[] = in_array($field['type'], ['multi_select'], true)
                        ? 'array'
                        : Rule::in($allowed);

                    if ($field['type'] === 'multi_select') {
                        $rules["payload.$key.*"] = ['string', Rule::in($allowed)];
                    }
                }
            }

            $rules["payload.$key"] = $fieldRules;
            $attributes["payload.$key"] = $field['label'] ?? $key;
        }

        $validated = Validator::make(['payload' => $payload], $rules, [], $attributes)->validate();

        return $validated['payload'] ?? [];
    }

    protected function createLeadFromSubmission(WebForm $form, array $payload): Lead
    {
        $settings = $form->settings ?? [];
        $mapping = $settings['lead_mapping'] ?? [];

        $leadData = [
            'tenant_id' => $form->tenant_id,
            'workspace_id' => $form->workspace_id,
            'name' => $this->mappedValue($payload, $mapping, 'name') ?: $this->fallbackLeadName($form, $payload),
            'email' => $this->mappedValue($payload, $mapping, 'email'),
            'phone' => $this->mappedValue($payload, $mapping, 'phone'),
            'company' => $this->mappedValue($payload, $mapping, 'company'),
            'notes' => $this->mappedValue($payload, $mapping, 'notes') ?: $this->submissionSummary($form, $payload),
            'source' => 'web_form',
            'campaign_id' => $settings['campaign_id'] ?? null,
            'marketing_stage_id' => $settings['marketing_stage_id'] ?? null,
            'status' => 'new',
            'department' => Department::Marketing->value,
        ];

        if (empty($leadData['marketing_stage_id'])) {
            $leadData['marketing_stage_id'] = PipelineStage::query()
                ->where('type', 'marketing')
                ->orderBy('sort_order')
                ->value('id');
        }

        $result = $this->leadResolver->findOrCreateFromData($leadData);
        $lead = $result['lead'];

        if ($result['created'] && $lead->marketing_stage_id) {
            $this->transitions->log('lead', $lead->id, null, $lead->marketing_stage_id);
        }

        if ($result['created']) {
            $this->automation->dispatch('lead.created', $lead, ['source' => 'web_form']);
        }

        return $lead;
    }

    protected function normalizeSchema(array $schema): array
    {
        $fields = collect($schema['fields'] ?? $schema)
            ->map(function ($field, $index) {
                $type = $field['type'] ?? 'text';
                $key = $field['key'] ?? Str::slug($field['label'] ?? "field-$index", '_');

                return [
                    'id' => $field['id'] ?? (string) Str::uuid(),
                    'key' => $key ?: "field_$index",
                    'type' => $type,
                    'label' => $field['label'] ?? 'فیلد جدید',
                    'placeholder' => $field['placeholder'] ?? null,
                    'required' => (bool) ($field['required'] ?? false),
                    'options' => $this->normalizeOptions($field['options'] ?? []),
                    'help_text' => $field['help_text'] ?? null,
                ];
            })
            ->values()
            ->all();

        return ['fields' => $fields];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'create_lead' => (bool) ($settings['create_lead'] ?? true),
            'campaign_id' => $settings['campaign_id'] ?? null,
            'marketing_stage_id' => $settings['marketing_stage_id'] ?? null,
            'redirect_url' => $settings['redirect_url'] ?? null,
            'success_message' => $settings['success_message'] ?? 'فرم با موفقیت ثبت شد.',
            'multi_step' => (bool) ($settings['multi_step'] ?? false),
            'branding' => $this->normalizeBrandingSettings($settings['branding'] ?? []),
            'lead_mapping' => $settings['lead_mapping'] ?? [
                'name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
                'company' => 'company',
                'notes' => 'message',
            ],
        ];
    }

    protected function normalizeBrandingSettings(array $branding): array
    {
        return [
            'brand_name' => $branding['brand_name'] ?? null,
            'headline' => $branding['headline'] ?? null,
            'subtitle' => $branding['subtitle'] ?? null,
            'logo_url' => $branding['logo_url'] ?? null,
            'primary_color' => $branding['primary_color'] ?? '#4A0E17',
            'accent_color' => $branding['accent_color'] ?? '#E8C57D',
            'background_color' => $branding['background_color'] ?? '#FFF7F0',
            'card_color' => $branding['card_color'] ?? '#FFFFFF',
        ];
    }

    protected function normalizeOptions(array $options): array
    {
        return collect($options)
            ->map(function ($option) {
                if (is_string($option)) {
                    return ['title' => $option, 'value' => $option];
                }

                return [
                    'title' => $option['title'] ?? $option['label'] ?? $option['value'] ?? '',
                    'value' => $option['value'] ?? $option['title'] ?? $option['label'] ?? '',
                ];
            })
            ->filter(fn ($option) => $option['value'] !== '')
            ->values()
            ->all();
    }

    protected function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $slug = $base;
        $counter = 2;

        while (WebForm::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected function mappedValue(array $payload, array $mapping, string $field): ?string
    {
        $key = $mapping[$field] ?? $field;
        $value = $payload[$key] ?? null;

        if (is_array($value)) {
            return implode('، ', $value);
        }

        return $value === null || $value === '' ? null : (string) $value;
    }

    protected function fallbackLeadName(WebForm $form, array $payload): string
    {
        return $payload['name'] ?? $payload['full_name'] ?? $payload['phone'] ?? $payload['email'] ?? "پاسخ فرم {$form->name}";
    }

    protected function submissionSummary(WebForm $form, array $payload): string
    {
        $labels = collect($form->schema['fields'] ?? [])->pluck('label', 'key');

        return collect($payload)
            ->map(function ($value, $key) use ($labels) {
                $value = is_array($value) ? implode('، ', $value) : $value;

                return ($labels[$key] ?? $key).': '.$value;
            })
            ->implode("\n");
    }
}
