<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\AppointmentNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Sends one reminder (SMS and/or email) for each approved appointment that
 * starts within the next `reminder_hours_before` hours. Scheduled hourly in
 * routes/console.php; `reminder_sent_at` guarantees a single reminder.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders {--dry-run : List what would be sent without sending}';

    protected $description = 'Send reminders for upcoming approved appointments (once per appointment)';

    public function handle(AppointmentNotifier $notifier): int
    {
        $smsOn   = settings('sms_enabled', false) && settings('notify_sms_appointment_reminder', true);
        $emailOn = (bool) settings('notify_email_appointments', false);

        if (! $smsOn && ! $emailOn) {
            $this->info('Reminders are turned off (SMS master switch / reminder toggle / email). Nothing sent.');

            return self::SUCCESS;
        }

        $hours = max(1, (int) settings('reminder_hours_before', 24));
        $now   = now();
        $until = $now->copy()->addHours($hours);
        $sent  = 0;

        Appointment::query()
            ->with('patient')
            ->where('status', 'approved')
            ->whereNull('reminder_sent_at')
            ->whereDate('appointment_date', '>=', $now->toDateString())
            ->whereDate('appointment_date', '<=', $until->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use ($now, $until, $notifier, &$sent) {
                foreach ($appointments as $appointment) {
                    $startsAt = Carbon::parse(
                        $appointment->appointment_date->toDateString().' '.($appointment->appointment_time ?: '00:00')
                    );

                    if ($startsAt->lt($now) || $startsAt->gt($until)) {
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("Would remind #{$appointment->id} ({$startsAt->format('M d, Y h:i A')})");
                        continue;
                    }

                    // Claim first (atomic) so overlapping runs can never double-send.
                    $claimed = Appointment::whereKey($appointment->id)
                        ->whereNull('reminder_sent_at')
                        ->update(['reminder_sent_at' => now()]);

                    if ($claimed) {
                        $notifier->notify('reminder', $appointment);
                        $sent++;
                    }
                }
            });

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
