<?php

namespace App\Console\Commands;

use App\Application\Reminder\ReminderProcessor;
use Illuminate\Console\Command;

class ProcessDueReminders extends Command
{
    protected $signature = 'reminders:process';

    protected $description = 'Send due CRM reminders (tasks, leads, deals, activities)';

    public function handle(ReminderProcessor $processor): int
    {
        $count = $processor->process();

        $this->info("Processed {$count} reminder(s).");

        return self::SUCCESS;
    }
}
