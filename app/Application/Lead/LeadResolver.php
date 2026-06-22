<?php

namespace App\Application\Lead;

use App\Application\Contact\ContactResolver;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;

class LeadResolver
{
    public function __construct(
        protected ContactResolver $contactResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{lead: Lead, created: bool}
     */
    public function findOrCreateFromData(array $data): array
    {
        $tenantId = $data['tenant_id'] ?? null;
        $contact = $this->contactResolver->findOrCreateFromLeadData($data, $tenantId, $data['workspace_id'] ?? null);
        $data['contact_id'] = $contact->id;

        $existingLead = Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contact->id)
            ->latest('id')
            ->first();

        if ($existingLead) {
            $this->mergeIntoLead($existingLead, $data);

            return ['lead' => $existingLead->fresh(), 'created' => false];
        }

        return [
            'lead' => Lead::create($data),
            'created' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mergeIntoLead(Lead $lead, array $data): void
    {
        $updates = [];

        foreach (['name', 'email', 'phone', 'company', 'job_title', 'city', 'assigned_to', 'contact_id'] as $field) {
            if (! empty($data[$field]) && empty($lead->{$field})) {
                $updates[$field] = $data[$field];
            }
        }

        if (! empty($data['notes'])) {
            $notes = trim((string) $data['notes']);
            $currentNotes = trim((string) ($lead->notes ?? ''));

            if ($currentNotes === '') {
                $updates['notes'] = $notes;
            } elseif (! str_contains($currentNotes, $notes)) {
                $updates['notes'] = $currentNotes."\n".$notes;
            }
        }

        if ($updates) {
            $lead->update($updates);
        }
    }
}
