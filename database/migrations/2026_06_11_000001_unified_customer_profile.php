<?php

use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('company');
            $table->string('city')->nullable()->after('job_title');
            $table->text('notes')->nullable()->after('city');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
        });

        Lead::query()->whereNull('contact_id')->orderBy('id')->each(function (Lead $lead) {
            $contact = null;

            if ($lead->email) {
                $contact = Contact::query()
                    ->where('tenant_id', $lead->tenant_id)
                    ->where('email', $lead->email)
                    ->first();
            }

            if (! $contact && $lead->phone) {
                $contact = Contact::query()
                    ->where('tenant_id', $lead->tenant_id)
                    ->where('phone', $lead->phone)
                    ->first();
            }

            if (! $contact) {
                $contact = Contact::create([
                    'tenant_id' => $lead->tenant_id,
                    'workspace_id' => $lead->workspace_id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'company' => $lead->company,
                    'job_title' => $lead->job_title,
                    'city' => $lead->city,
                    'notes' => $lead->notes,
                    'assigned_to' => $lead->assigned_to,
                ]);
            }

            $lead->update(['contact_id' => $contact->id]);
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'city', 'notes']);
        });
    }
};
