<?php

namespace App\Console\Commands;

use App\Services\LiveStream\LiveStreamService;
use Illuminate\Console\Command;

class ProcessScheduledLiveStreams extends Command
{
    protected $signature = 'live-streams:process';

    protected $description = 'Auto-start/end scheduled live streams and send reminder notifications';

    public function handle(LiveStreamService $streams): int
    {
        $streams->processReminderNotifications();
        $streams->processAutoStartEnd();

        $this->info('Processed scheduled live streams.');

        return self::SUCCESS;
    }
}
