<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentTimeSlot;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Appointment time slots (label, start / end time, capacity, weekdays).
 * A slot that appointments still use cannot be deleted: deactivate it instead,
 * which hides it from new bookings but keeps existing appointments intact.
 */
class AppointmentSlotController extends Controller
{
    public function index()
    {
        $slots = AppointmentTimeSlot::orderBy('slot_time')->get();

        $usage = Appointment::selectRaw('appointment_time, count(*) as n')
            ->groupBy('appointment_time')
            ->pluck('n', 'appointment_time');

        $upcoming = Appointment::whereDate('appointment_date', '>=', today())
            ->whereIn('status', ['pending', 'approved'])
            ->selectRaw('appointment_time, count(*) as n')
            ->groupBy('appointment_time')
            ->pluck('n', 'appointment_time');

        return view('admin.appointment-slots.index', compact('slots', 'usage', 'upcoming'));
    }

    public function create()
    {
        $minutes = (int) settings('appointment_slot_minutes', 30);

        return view('admin.appointment-slots.form', [
            'slot' => new AppointmentTimeSlot([
                'slot_time' => '08:00:00',
                'end_time'  => date('H:i:s', strtotime('08:00') + $minutes * 60),
                'max_appointments' => 5,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $slot = AppointmentTimeSlot::create($data);

        AuditLogService::log(
            action: 'created',
            module: 'appointment-slots',
            description: "Added appointment slot {$slot->display_label} (capacity {$slot->max_appointments}, {$slot->weekdays_label})",
            newValues: $data,
        );

        return redirect()->route('admin.appointment-slots.index')->with('success', 'Time slot added.');
    }

    public function edit(AppointmentTimeSlot $appointmentSlot)
    {
        return view('admin.appointment-slots.form', ['slot' => $appointmentSlot]);
    }

    public function update(Request $request, AppointmentTimeSlot $appointmentSlot)
    {
        $data = $this->validated($request, $appointmentSlot);

        // Appointments store the start time, so it cannot move once used.
        if ($data['slot_time'] !== $appointmentSlot->slot_time && $appointmentSlot->appointmentsCount() > 0) {
            return back()->withInput()->withErrors([
                'slot_time' => 'Appointments already use this start time. Add a new slot for the new time and deactivate this one.',
            ]);
        }

        $old = $appointmentSlot->only(array_keys($data));
        $appointmentSlot->update($data);

        AuditLogService::log(
            action: 'updated',
            module: 'appointment-slots',
            description: "Updated appointment slot {$appointmentSlot->display_label}",
            oldValues: $old,
            newValues: $appointmentSlot->only(array_keys($data)),
        );

        return redirect()->route('admin.appointment-slots.index')->with('success', 'Time slot updated.');
    }

    public function toggle(AppointmentTimeSlot $appointmentSlot)
    {
        $appointmentSlot->update(['is_active' => ! $appointmentSlot->is_active]);

        AuditLogService::log(
            action: 'updated',
            module: 'appointment-slots',
            description: ($appointmentSlot->is_active ? 'Activated' : 'Deactivated')." appointment slot {$appointmentSlot->display_label}",
        );

        return back()->with('success', $appointmentSlot->is_active ? 'Time slot activated.' : 'Time slot deactivated. It is hidden from new bookings.');
    }

    public function destroy(AppointmentTimeSlot $appointmentSlot)
    {
        $used = $appointmentSlot->appointmentsCount();
        if ($used > 0) {
            return back()->with('error', "This slot is used by {$used} appointment(s) and cannot be deleted. Deactivate it instead.");
        }

        $label = $appointmentSlot->display_label;
        $appointmentSlot->delete();

        AuditLogService::log(action: 'deleted', module: 'appointment-slots', description: "Deleted appointment slot {$label}");

        return redirect()->route('admin.appointment-slots.index')->with('success', 'Time slot deleted.');
    }

    private function validated(Request $request, ?AppointmentTimeSlot $current = null): array
    {
        $request->merge([
            'slot_time' => substr((string) $request->input('slot_time'), 0, 5),
            'end_time'  => substr((string) $request->input('end_time'), 0, 5),
        ]);

        $validator = validator($request->all(), [
            'label'            => ['nullable', 'string', 'max:60'],
            'slot_time'        => ['required', 'date_format:H:i'],
            'end_time'         => ['required', 'date_format:H:i', 'after:slot_time'],
            'max_appointments' => ['required', 'integer', 'min:1', 'max:500'],
            'weekdays'         => ['nullable', 'array'],
            'weekdays.*'       => ['integer', Rule::in(array_keys(AppointmentTimeSlot::WEEKDAYS))],
            'is_active'        => ['nullable', 'boolean'],
        ], [
            'slot_time.required' => 'Start time is required.',
            'end_time.after'     => 'The end time must be after the start time.',
            'weekdays.*.in'      => 'Choose valid weekdays.',
        ], ['slot_time' => 'start time', 'max_appointments' => 'capacity']);

        $validator->after(function (Validator $v) use ($request, $current) {
            if ($v->errors()->has('slot_time')) {
                return;
            }
            $exists = AppointmentTimeSlot::where('slot_time', $request->input('slot_time').':00')
                ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
                ->exists();
            if ($exists) {
                $v->errors()->add('slot_time', 'A slot already starts at this time.');
            }
        });

        $data = $validator->validate();

        $days = array_values(array_unique(array_map('intval', $data['weekdays'] ?? [])));
        sort($days);

        return [
            'label'            => $data['label'] ?? null,
            'slot_time'        => $data['slot_time'].':00',
            'end_time'         => $data['end_time'].':00',
            'max_appointments' => (int) $data['max_appointments'],
            // Every weekday ticked (or none) = no restriction.
            'weekdays'         => ($days === [] || count($days) === 7) ? null : $days,
            'is_active'        => $request->boolean('is_active'),
        ];
    }
}
