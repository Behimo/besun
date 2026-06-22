<?php

namespace App\Application\Automation\Actions;

use App\Application\Sms\SmsService;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SendSmsAction
{
    public function __construct(
        protected SmsService $sms,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(Model $entity, array $params, User $actor, Tenant $tenant): array
    {
        if (! $tenant->hasModule('mod-sms')) {
            throw new \RuntimeException('ماژول پیامک فعال نیست.');
        }

        $message = $params['message'] ?? '';

        if ($message === '') {
            throw new \RuntimeException('متن پیامک مشخص نشده است.');
        }

        $phone = $entity->getAttribute('phone');

        if (! $phone && $entity->relationLoaded('contact')) {
            $phone = $entity->contact?->phone;
        }

        if (! $phone && method_exists($entity, 'contact')) {
            $entity->loadMissing('contact');
            $phone = $entity->contact?->phone;
        }

        if (! $phone) {
            throw new \RuntimeException('شماره تلفن برای ارسال پیامک یافت نشد.');
        }

        $message = $this->replacePlaceholders($message, $entity);

        $payload = [
            'message' => $message,
            'phone' => $phone,
            'related_type' => $entity instanceof Lead ? Lead::class : ($entity instanceof Deal ? Deal::class : null),
            'related_id' => $entity->id,
        ];

        if ($entity instanceof Lead) {
            $payload['lead_id'] = $entity->id;
            $payload['contact_id'] = $entity->contact_id;
        }

        if ($entity instanceof Deal) {
            $payload['contact_id'] = $entity->contact_id;
        }

        $sms = $this->sms->send($actor, $payload);

        return ['sms_message_id' => $sms->id];
    }

    protected function replacePlaceholders(string $message, Model $entity): string
    {
        $stageName = '';

        if ($entity instanceof Lead && $entity->marketing_stage_id) {
            $stageName = PipelineStage::find($entity->marketing_stage_id)?->name ?? '';
        }

        if ($entity instanceof Deal && $entity->pipeline_stage_id) {
            $stageName = PipelineStage::find($entity->pipeline_stage_id)?->name ?? '';
        }

        $replacements = [
            '{{name}}' => (string) ($entity->getAttribute('name') ?? $entity->getAttribute('title') ?? ''),
            '{{phone}}' => (string) ($entity->getAttribute('phone') ?? ''),
            '{{company}}' => (string) ($entity->getAttribute('company') ?? ''),
            '{{stage}}' => $stageName,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
}
