<?php

namespace SabitAhmad\SteadFast\Commands;

use Illuminate\Console\Command;
use SabitAhmad\SteadFast\Models\SteadfastLog;

class SteadfastCleanupCommand extends Command
{
    protected $signature = 'steadfast:cleanup {--force : Force cleanup without confirmation}';

    protected $description = 'Clean up old Steadfast API logs';

    public function handle(): void
    {
        $keepDays = config('steadfast.logging.keep_logs_days', 30);
        $oldLogsCount = SteadfastLog::where('created_at', '<=', now()->subDays($keepDays))->count();

        if ($oldLogsCount === 0) {
            $this->info('No old logs found to clean up.');

            return;
        }

        $this->info("Found {$oldLogsCount} logs older than {$keepDays} days.");

        if (! $this->option('force') && ! $this->confirm('Do you want to delete these logs?')) {
            $this->info('Cleanup cancelled.');

            return;
        }

        $deletedCount = SteadfastLog::cleanup();

        $this->info("Successfully deleted {$deletedCount} old log entries.");
    }
}
