<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Support\PhoneNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeCrmCustomersCommand extends Command
{
    protected $signature = 'crm:dedupe-customers {--tenant= : Specific tenant ID}';

    protected $description = 'Merge duplicate contacts by phone and duplicate leads per contact';

    public function handle(): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $normalizedPhones = $this->normalizeContactPhones($tenantId);
        $backfilledDepartments = $this->backfillContactDepartments($tenantId);
        $mergedContacts = $this->mergeDuplicateContacts($tenantId);
        $mergedLeads = $this->mergeDuplicateLeads($tenantId);

        $this->info("Normalized phones: {$normalizedPhones}");
        $this->info("Backfilled contact departments: {$backfilledDepartments}");
        $this->info("Merged duplicate contacts: {$mergedContacts}");
        $this->info("Merged duplicate leads: {$mergedLeads}");

        return self::SUCCESS;
    }

    protected function normalizeContactPhones(?int $tenantId): int
    {
        $count = 0;

        Contact::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('phone')
            ->orderBy('id')
            ->each(function (Contact $contact) use (&$count) {
                $normalized = PhoneNormalizer::normalize($contact->phone);

                if ($normalized && $normalized !== $contact->phone) {
                    $contact->update(['phone' => $normalized]);
                    $count++;
                }
            });

        return $count;
    }

    protected function backfillContactDepartments(?int $tenantId): int
    {
        return Contact::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNull('department')
            ->update(['department' => 'sales']);
    }

    protected function mergeDuplicateContacts(?int $tenantId): int
    {
        $merged = 0;

        $groups = Contact::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('phone')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Contact $contact) => $contact->tenant_id.'|'.PhoneNormalizer::normalize($contact->phone));

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $primary = $group->first();
            $duplicates = $group->slice(1);

            foreach ($duplicates as $duplicate) {
                $this->reassignContactReferences($duplicate->id, $primary->id);
                $duplicate->delete();
                $merged++;
            }
        }

        return $merged;
    }

    protected function mergeDuplicateLeads(?int $tenantId): int
    {
        $merged = 0;

        $groups = Lead::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('contact_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Lead $lead) => $lead->tenant_id.'|'.$lead->contact_id);

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $primary = $group->sortByDesc(fn (Lead $lead) => $lead->status === 'converted')->first();
            $duplicates = $group->where('id', '!=', $primary->id);

            foreach ($duplicates as $duplicate) {
                $this->reassignLeadReferences($duplicate->id, $primary->id);
                $duplicate->delete();
                $merged++;
            }
        }

        return $merged;
    }

    protected function reassignContactReferences(int $fromId, int $toId): void
    {
        DB::table('leads')->where('contact_id', $fromId)->update(['contact_id' => $toId]);
        DB::table('deals')->where('contact_id', $fromId)->update(['contact_id' => $toId]);
        DB::table('quotes')->where('contact_id', $fromId)->update(['contact_id' => $toId]);
        DB::table('woocommerce_orders')->where('contact_id', $fromId)->update(['contact_id' => $toId]);
        DB::table('sms_message_recipients')->where('contact_id', $fromId)->update(['contact_id' => $toId]);

        DB::table('crm_entity_products')
            ->where('entity_type', 'contact')
            ->where('entity_id', $fromId)
            ->update(['entity_id' => $toId]);
    }

    protected function reassignLeadReferences(int $fromId, int $toId): void
    {
        DB::table('deals')->where('lead_id', $fromId)->update(['lead_id' => $toId]);
        DB::table('quotes')->where('lead_id', $fromId)->update(['lead_id' => $toId]);
        DB::table('woocommerce_orders')->where('lead_id', $fromId)->update(['lead_id' => $toId]);
        DB::table('web_form_submissions')->where('lead_id', $fromId)->update(['lead_id' => $toId]);
        DB::table('sms_message_recipients')->where('lead_id', $fromId)->update(['lead_id' => $toId]);

        DB::table('crm_entity_products')
            ->where('entity_type', 'lead')
            ->where('entity_id', $fromId)
            ->update(['entity_id' => $toId]);
    }
}
