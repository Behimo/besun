<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;

trait NormalizesCrmRelatedType
{
    protected function normalizeRelatedType(?string $type): ?string
    {
        return match ($type) {
            'contact', Contact::class => Contact::class,
            'lead', Lead::class => Lead::class,
            'deal', Deal::class => Deal::class,
            default => $type,
        };
    }
}
