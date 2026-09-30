<?php

namespace App\Http\Controllers;

use App\Models\SpecialistVisit;
use App\Services\AuditLogService;
use App\Support\DisplayFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Doctor / dentist clinic days (SSCMS calendar/schedule_specialist_visit.php).
 * SSCMS could only add visits; here they can also be edited, completed,
 * cancelled and deleted.
 */
class SpecialistVisitController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-specialist-visits');

        $scope = in_array($request->query('scope'), ['upcoming', 'past', 'all'], true) ? $request->query('scope') : 'upcoming';
        $type  = (string) $request->query('type', '');

        $visits = SpecialistVisit::query()
            ->withCount(['appointments as booked_count' => fn ($q) => $q->whereIn('status', ['pending', 'approved'])])
            ->when($scope === 'upcoming', fn ($q) => $q->whereDate('visit_date', '>=', today())->orderBy('visit_date')->orderBy('start_time'))
            ->when($scope === 'past', fn ($q) => $q->whereDate('visit_date', '<', today())->orderByDesc('visit_date'))
            ->when($scope === 'all', fn ($q) => $q->orderByDesc('visit_date'))
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->paginate(DisplayFormat::perPage(20))
            ->withQueryString();

        return view('specialist-visits.index', [
            'visits' => $visits,
            'scope'  => $scope,
            'type'   => $type,
            'types'  => SpecialistVisit::types(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('manage-specialist-visits');

        return view('specialist-visits.form', [
            'visit' => new SpecialistVisit([
                'visit_date' => $request->query('date') ?: today()->addDay()->toDateString(),
                'start_time' => '08:00',
                'end_time'   => '12:00',
            ]),
            'types' => SpecialistVisit::types(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-specialist-visits');

        $data = $this->validated($request);
        $data['created_by'] = auth()->id();

        $visit = SpecialistVisit::create($data);

        AuditLogService::log(
            action: 'created',
            module: 'specialist-visits',
            description: "Scheduled {$visit->type} visit ({$visit->specialist_name}) on ".$visit->visit_date->format('M d, Y').", {$visit->time_range}",
            newValues: $visit->only(['type', 'specialist_name', 'visit_date', 'start_time', 'end_time', 'capacity']),
        );

        return redirect()->route('specialist-visits.index')->with('success', 'Specialist visit scheduled.');
    }

    public function show(SpecialistVisit $specialistVisit)
    {
        $this->authorize('view-specialist-visits');

        $specialistVisit->load(['appointments' => fn ($q) => $q->with('patient')->orderBy('appointment_time')]);

        return view('specialist-visits.show', ['visit' => $specialistVisit]);
    }

    public function edit(SpecialistVisit $specialistVisit)
    {
        $this->authorize('manage-specialist-visits');

        return view('specialist-visits.form', [
            'visit' => $specialistVisit,
            'types' => SpecialistVisit::types(),
        ]);
    }

    public function update(Request $request, SpecialistVisit $specialistVisit)
    {
        $this->authorize('manage-specialist-visits');

        $data = $this->validated($request, $specialistVisit);
        $data['updated_by'] = auth()->id();

        $old = $specialistVisit->only(array_keys($data));
        $specialistVisit->update($data);

        AuditLogService::log(
            action: 'updated',
            module: 'specialist-visits',
            description: "Updated {$specialistVisit->type} visit #{$specialistVisit->id} on ".$specialistVisit->visit_date->format('M d, Y'),
            oldValues: $old,
            newValues: $specialistVisit->only(array_keys($data)),
        );

        return redirect()->route('specialist-visits.show', $specialistVisit)->with('success', 'Specialist visit updated.');
    }

    public function destroy(SpecialistVisit $specialistVisit)
    {
        $this->authorize('manage-specialist-visits');

        $booked = $specialistVisit->bookedCount();
        if ($booked > 0) {
            return back()->with('error', "{$booked} appointment(s) are booked for this visit. Cancel the visit instead, or move those appointments first.");
        }

        DB::transaction(function () use ($specialistVisit) {
            $label = "{$specialistVisit->type} visit ({$specialistVisit->specialist_name}) on ".$specialistVisit->visit_date->format('M d, Y');
            $specialistVisit->delete();

            AuditLogService::log(action: 'deleted', module: 'specialist-visits', description: "Deleted {$label}");
        });

        return redirect()->route('specialist-visits.index')->with('success', 'Specialist visit deleted.');
    }

    private function validated(Request $request, ?SpecialistVisit $current = null): array
    {
        $types = SpecialistVisit::types();
        if ($current && ! in_array($current->type, $types, true)) {
            $types[] = $current->type;
        }

        $rules = [
            'type'            => ['required', 'string', Rule::in($types)],
            'specialist_name' => ['required', 'string', 'max:150'],
            'visit_date'      => ['required', 'date', $current ? 'after_or_equal:2000-01-01' : 'after_or_equal:today'],
            'start_time'      => ['required', 'date_format:H:i'],
            'end_time'        => ['required', 'date_format:H:i', 'after:start_time'],
            'capacity'        => ['nullable', 'integer', 'min:1', 'max:500'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
        if ($current) {
            $rules['status'] = ['required', Rule::in(array_keys(SpecialistVisit::STATUSES))];
        }

        // <input type="time"> may send HH:MM:SS
        $request->merge([
            'start_time' => substr((string) $request->input('start_time'), 0, 5),
            'end_time'   => substr((string) $request->input('end_time'), 0, 5),
        ]);

        $validator = validator($request->all(), $rules, [
            'visit_date.after_or_equal' => 'The visit date cannot be in the past.',
            'end_time.after'            => 'The end time must be after the start time.',
        ]);

        // Same specialist cannot be booked twice at overlapping times on a day.
        $validator->after(function (Validator $v) use ($request, $current) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $overlap = SpecialistVisit::query()
                ->whereDate('visit_date', $request->input('visit_date'))
                ->where('status', '!=', 'cancelled')
                ->whereRaw('LOWER(specialist_name) = ?', [mb_strtolower(trim((string) $request->input('specialist_name')))])
                ->where('start_time', '<', $request->input('end_time').':00')
                ->where('end_time', '>', $request->input('start_time').':00')
                ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
                ->exists();
            if ($overlap) {
                $v->errors()->add('start_time', 'This specialist already has a visit that overlaps these hours on that date.');
            }
        });

        $data = $validator->validate();
        $data['start_time'] .= ':00';
        $data['end_time']   .= ':00';

        return $data;
    }
}
