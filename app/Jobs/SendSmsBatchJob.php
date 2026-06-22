<?php

namespace App\Jobs;

use App\Application\Sms\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSmsBatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $smsMessageId,
    ) {}

    public function handle(SmsService $smsService): void
    {
        $smsService->processMessage($this->smsMessageId);
    }
}
