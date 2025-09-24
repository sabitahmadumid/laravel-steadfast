<?php

namespace SabitAhmad\SteadFast\Commands;

use Illuminate\Console\Command;
use SabitAhmad\SteadFast\Models\SteadfastLog;

class SteadfastStatsCommand extends Command
{
    protected $signature = 'steadfast:stats {--hours=24 : Number of hours to include in stats}';

    protected $description = 'Display Steadfast API usage statistics';

    public function handle(): void
    {
        $hours = (int) $this->option('hours');
        $stats = SteadfastLog::getStats($hours);

        $this->info("Steadfast API Statistics (Last {$hours} hours)");
        $this->info(str_repeat('=', 50));

        $this->table(['Metric', 'Value'], [
            ['Total Requests', number_format($stats['total'])],
            ['Successful', number_format($stats['successful'])],
            ['Errors', number_format($stats['errors'])],
            ['Success Rate', $stats['total'] > 0 ? round(($stats['successful'] / $stats['total']) * 100, 2).'%' : 'N/A'],
            ['Bulk Operations', number_format($stats['bulk_operations'])],
            ['Average Duration', isset($stats['average_duration']) ? round($stats['average_duration'], 2).'ms' : 'N/A'],
        ]);

        if (! empty($stats['endpoints'])) {
            $this->info("\nTop Endpoints:");
            $endpointData = [];
            foreach ($stats['endpoints'] as $endpoint => $count) {
                $endpointData[] = [$endpoint, number_format($count)];
            }
            $this->table(['Endpoint', 'Requests'], $endpointData);
        }

        if (! empty($stats['error_types'])) {
            $this->info("\nError Types:");
            $errorData = [];
            foreach ($stats['error_types'] as $statusCode => $count) {
                $errorData[] = [$statusCode, number_format($count)];
            }
            $this->table(['Status Code', 'Count'], $errorData);
        }
    }
}
