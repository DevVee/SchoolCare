<?php

namespace App\Jobs;

use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Delivers one queued SMS (an sms_logs row in "pending" state).
 * Retries transient failures (network / Semaphore 5xx) up to 3 times.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $smsLogId) {}

    /** Seconds to wait before each retry. */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(SmsService $sms): void
    {
        $log = SmsLog::find($this->smsLogId);

        if (! $log || $log->status !== 'pending') {
            return; // already handled (or deleted)
        }

        // Only rethrow (→ retry) when running on a real queue with attempts left;
        // on the sync driver an exception would bubble into the web request.
        $canRetry = $this->job !== null
            && $this->job->getConnectionName() !== 'sync'
            && $this->attempts() < $this->tries;

        try {
            $sms->deliver($log, throwOnTransient: $canRetry);
        } catch (\Throwable $e) {
            if ($canRetry) {
                throw $e;
            }
            $sms->fail($log, 'Delivery failed: '.Str::limit($e->getMessage(), 300));
        }
    }

    /** Called after the final attempt fails. */
    public function failed(?\Throwable $e): void
    {
        $log = SmsLog::find($this->smsLogId);

        if ($log && $log->status === 'pending') {
            app(SmsService::class)->fail($log, 'Delivery failed after retries: '.Str::limit((string) $e?->getMessage(), 300));
        }
    }
}
