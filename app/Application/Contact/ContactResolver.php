<?php

namespace App\Application\Contact;

use App\Domain\Shared\Enums\Department;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;

class ContactResolver
{
    public function findOrCreateFromLeadData(array $data, ?int $tenantId = null, ?int $workspaceId = null): Contact
    {
        $tenantId = $tenantId ?? $data['tenant_id'] ?? null;
        $workspaceId = $workspaceId ?? $data['workspace_id'] ?? null;
        $data = $this->normalizeLeadData($data);

        $contact = null;

        if (! empty($data['email'])) {
            $contact = Contact::query()
                ->where('tenant_id', $tenantId)
                ->where('email', $data['email'])
                ->first();
        }

        if (! $contact && ! empty($data['phone'])) {
            $contact = $this->findByPhone($tenantId, $data['phone']);
        }

        if ($contact) {
            $this->mergeIntoContact($contact, $data);

            return $contact->fresh();
        }

        return Contact::create([
            'tenant_id' => $tenantId,
            'workspace_id' => $workspaceId,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'city' => $data['city'] ?? null,
            'notes' => $data['notes'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'department' => $data['department'] ?? Department::Sales->value,
        ]);
    }

    public function syncLeadToContact(Lead $lead): Contact
    {
        if ($lead->contact_id) {
            $contact = Contact::find($lead->contact_id);

            if ($contact) {
                $this->mergeIntoContact($contact, $lead->only([
                    'name', 'email', 'phone', 'company', 'job_title', 'city', 'notes', 'assigned_to',
                ]));

                return $contact->fresh();
            }
        }

        $contact = $this->findOrCreateFromLeadData(
            $lead->only(['name', 'email', 'phone', 'company', 'job_title', 'city', 'notes', 'assigned_to', 'tenant_id', 'workspace_id', 'assigned_to']),
            $lead->tenant_id,
            $lead->workspace_id,
        );

        if (! $lead->contact_id) {
            $lead->update(['contact_id' => $contact->id]);
        }

        return $contact;
    }

    public function findByPhone(?int $tenantId, string $phone): ?Contact
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if (! $normalized) {
            return Contact::query()
                ->where('tenant_id', $tenantId)
                ->where('phone', $phone)
                ->first();
        }

        return $this->queryByNormalizedPhone($tenantId, $normalized)->first();
    }

    protected function queryByNormalizedPhone(?int $tenantId, string $normalized): Builder
    {
        return Contact::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('phone')
            ->where(function (Builder $query) use ($normalized) {
                $query->where('phone', $normalized)
                    ->orWhere('phone', ltrim($normalized, '0'))
                    ->orWhere('phone', '+98'.substr($normalized, 1))
                    ->orWhere('phone', '98'.substr($normalized, 1));
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeLeadData(array $data): array
    {
        if (! empty($data['phone'])) {
            $data['phone'] = PhoneNormalizer::normalize($data['phone']) ?? $data['phone'];
        }

        return $data;
    }

    protected function mergeIntoContact(Contact $contact, array $data): void
    {
        $data = $this->normalizeLeadData($data);
        $updates = [];

        foreach (['name', 'email', 'phone', 'company', 'job_title', 'city', 'notes', 'assigned_to'] as $field) {
            if (! empty($data[$field]) && empty($contact->{$field})) {
                $updates[$field] = $data[$field];
            }
        }

        if (empty($contact->department)) {
            $updates['department'] = $data['department'] ?? Department::Sales->value;
        }

        if ($updates) {
            $contact->update($updates);
        }
    }
}
