<?php

namespace SabitAhmad\SteadFast\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

class SteadfastLog extends Model
{
    use Prunable;

    protected $table = 'steadfast_logs';

    protected $guarded = [];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        $keepDays = config('steadfast.logging.keep_logs_days', 30);
        return static::where('created_at', '<=', now()->subDays($keepDays));
    }

    /**
     * Prepare the model for pruning.
     */
    protected function pruning(): void
    {
        // Additional cleanup if needed
    }

    /**
     * Scope for filtering by type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for filtering by status code
     */
    public function scopeWithStatusCode(Builder $query, int $statusCode): Builder
    {
        return $query->where('status_code', $statusCode);
    }

    /**
     * Scope for errors only
     */
    public function scopeErrors(Builder $query): Builder
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * Scope for successful requests only
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status_code', '<', 400);
    }

    /**
     * Scope for recent logs
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for bulk operations
     */
    public function scopeBulkOperations(Builder $query): Builder
    {
        return $query->whereIn('type', ['bulk_order', 'bulk_job_start', 'bulk_job_success', 'bulk_job_error', 'bulk_job_failed']);
    }

    /**
     * Get formatted duration if available
     */
    public function getFormattedDurationAttribute(): string
    {
        $duration = $this->response['duration_ms'] ?? null;
        
        if ($duration === null) {
            return 'N/A';
        }

        if ($duration < 1000) {
            return round($duration, 2) . 'ms';
        }

        return round($duration / 1000, 2) . 's';
    }

    /**
     * Check if this log represents an error
     */
    public function isError(): bool
    {
        return $this->status_code >= 400 || !empty($this->error);
    }

    /**
     * Check if this log represents a successful operation
     */
    public function isSuccessful(): bool
    {
        return $this->status_code < 400 && empty($this->error);
    }

    /**
     * Get the endpoint name without parameters
     */
    public function getEndpointNameAttribute(): string
    {
        $endpoint = $this->endpoint;
        
        // Remove dynamic parts like IDs
        $endpoint = preg_replace('/\/\d+/', '/{id}', $endpoint);
        $endpoint = preg_replace('/\/[a-zA-Z0-9_-]+$/', '/{identifier}', $endpoint);
        
        return $endpoint;
    }

    /**
     * Get log statistics
     */
    public static function getStats(int $hours = 24): array
    {
        $query = static::recent($hours);
        
        return [
            'total' => $query->count(),
            'successful' => $query->successful()->count(),
            'errors' => $query->errors()->count(),
            'bulk_operations' => $query->bulkOperations()->count(),
            'average_duration' => $query->whereNotNull('response->duration_ms')
                ->avg('response->duration_ms'),
            'endpoints' => $query->groupBy('endpoint')
                ->selectRaw('endpoint, count(*) as count')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'endpoint')
                ->toArray(),
            'error_types' => $query->errors()
                ->groupBy('status_code')
                ->selectRaw('status_code, count(*) as count')
                ->orderByDesc('count')
                ->pluck('count', 'status_code')
                ->toArray(),
        ];
    }

    /**
     * Clean up old logs (called by pruning)
     */
    public static function cleanup(): int
    {
        if (!config('steadfast.logging.cleanup_logs', true)) {
            return 0;
        }

        $keepDays = config('steadfast.logging.keep_logs_days', 30);
        return static::where('created_at', '<=', now()->subDays($keepDays))->delete();
    }
}
