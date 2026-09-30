<?php

namespace App\Models;

use App\Support\DisplayFormat;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A bookable appointment time. slot_time is the start time (stored on
 * appointments.appointment_time) and max_appointments is the capacity.
 * weekdays: ISO weekday numbers (1 = Monday ... 7 = Sunday); null = every day.
 */
class AppointmentTimeSlot extends Model
{
    protected $fillable = ['label', 'slot_time', 'end_time', 'max_appointments', 'weekdays', 'is_active'];

    public const WEEKDAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'max_appointments' => 'integer',
            'weekdays'         => 'array',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('slot_time');
    }

    public function getFormattedTimeAttribute(): string
    {
        return date('h:i A', strtotime($this->slot_time));
    }

    /** "Morning (08:00 AM to 08:30 AM)" or just the time range. */
    public function getDisplayLabelAttribute(): string
    {
        $range = DisplayFormat::timeRange($this->slot_time, $this->end_time);

        return $this->label ? "{$this->label} ({$range})" : $range;
    }

    /** Is this slot offered on the given date's weekday? */
    public function isOfferedOn(CarbonInterface|string $date): bool
    {
        $days = $this->weekdays;
        if (! is_array($days) || $days === []) {
            return true;
        }
        $iso = ($date instanceof CarbonInterface ? $date : Carbon::parse($date))->dayOfWeekIso;

        return in_array($iso, array_map('intval', $days), true);
    }

    public function getWeekdaysLabelAttribute(): string
    {
        $days = $this->weekdays;
        if (! is_array($days) || $days === [] || count($days) === 7) {
            return 'Every day';
        }
        sort($days);

        return implode(', ', array_map(fn ($d) => self::WEEKDAYS[(int) $d] ?? $d, $days));
    }

    /** Appointments that still hold this slot (any date, not deleted). */
    public function appointmentsCount(): int
    {
        return Appointment::where('appointment_time', $this->slot_time)->count();
    }
}
