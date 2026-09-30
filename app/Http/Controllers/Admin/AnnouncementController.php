<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditLogService;
use App\Services\LandingContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administration → Website, Advisories tab: notices for the public website,
 * the staff dashboard, or both, with optional start and end times.
 * Permission: manage-landing (route).
 */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.website.advisories.index', [
            'advisories' => Announcement::query()->with('createdBy:id,name')->ordered()->get(),
            'counts'     => WebsiteController::tabCounts(),
        ]);
    }

    public function create(): View
    {
        return view('admin.website.advisories.form', [
            'advisory' => new Announcement(['type' => 'advisory', 'audience' => 'public', 'is_enabled' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        // New advisories go to the top of the list.
        $data['sort_order'] = (int) Announcement::query()->min('sort_order') - 1;

        $advisory = Announcement::create($data);

        AuditLogService::log(
            action: 'created',
            module: 'website',
            description: 'Posted advisory "'.Str::limit($advisory->title, 80).'" ('.$advisory->audience_label.')',
            newValues: $advisory->only(['title', 'type', 'audience', 'starts_at', 'ends_at', 'is_enabled']),
        );

        return redirect()->route('admin.website.advisories.index')->with('success', 'Advisory saved.');
    }

    public function edit(Announcement $advisory): View
    {
        return view('admin.website.advisories.form', ['advisory' => $advisory]);
    }

    public function update(Request $request, Announcement $advisory): RedirectResponse
    {
        $data = $this->validated($request);
        $old = $advisory->only(array_keys($data));

        $advisory->update($data);

        AuditLogService::log(
            action: 'updated',
            module: 'website',
            description: 'Updated advisory "'.Str::limit($advisory->title, 80).'"',
            oldValues: $old,
            newValues: $advisory->only(array_keys($data)),
        );

        return redirect()->route('admin.website.advisories.index')->with('success', 'Advisory updated.');
    }

    public function toggle(Announcement $advisory): RedirectResponse
    {
        $advisory->update(['is_enabled' => ! $advisory->is_enabled]);

        AuditLogService::log(
            action: 'updated',
            module: 'website',
            description: ($advisory->is_enabled ? 'Turned on' : 'Turned off').' advisory "'.Str::limit($advisory->title, 80).'"',
        );

        return back()->with('success', $advisory->is_enabled ? 'Advisory turned on.' : 'Advisory turned off. It is no longer shown.');
    }

    public function move(Request $request, Announcement $advisory): RedirectResponse
    {
        $direction = $request->input('direction') === 'up' ? -1 : 1;

        DB::transaction(function () use ($advisory, $direction) {
            $ids = Announcement::query()->ordered()->pluck('id')->all();
            $pos = array_search($advisory->id, $ids, true);
            $swap = $pos + $direction;
            if ($pos === false || $swap < 0 || $swap >= count($ids)) {
                return;
            }
            [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
            foreach ($ids as $i => $id) {
                Announcement::query()->whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        LandingContent::forget();

        AuditLogService::log(
            action: 'updated',
            module: 'website',
            description: 'Moved advisory "'.Str::limit($advisory->title, 80).'" '.($direction < 0 ? 'up' : 'down'),
        );

        return back();
    }

    public function destroy(Announcement $advisory): RedirectResponse
    {
        $title = $advisory->title;
        $advisory->delete();

        AuditLogService::log(action: 'deleted', module: 'website', description: 'Deleted advisory "'.Str::limit($title, 80).'"');

        return redirect()->route('admin.website.advisories.index')->with('success', 'Advisory deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:150'],
            'body'       => ['nullable', 'string', 'max:2000'],
            'type'       => ['required', Rule::in(array_keys(Announcement::TYPES))],
            'audience'   => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'link_label' => ['nullable', 'string', 'max:60'],
            'link_url'   => ['nullable', 'url:http,https', 'max:300', 'required_with:link_label'],
            'starts_at'  => ['nullable', 'date'],
            'ends_at'    => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_enabled' => ['nullable', 'boolean'],
        ], [
            'ends_at.after_or_equal' => 'The end must be after the start.',
        ], [
            'title' => 'title', 'body' => 'message', 'link_url' => 'link address',
            'starts_at' => 'start', 'ends_at' => 'end', 'audience' => 'where to show it',
        ]);

        $data['is_enabled'] = $request->boolean('is_enabled');
        foreach (['body', 'link_label', 'link_url', 'starts_at', 'ends_at'] as $key) {
            $data[$key] = filled($data[$key] ?? null) ? $data[$key] : null;
        }

        return $data;
    }
}
