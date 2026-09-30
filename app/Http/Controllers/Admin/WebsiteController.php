<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SavesSettingsGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLandingSettingsRequest;
use App\Models\Announcement;
use App\Models\LandingItem;
use App\Services\LandingContent;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Administration → Website, "Page content" tab: the Website settings group
 * (sections, top of the page, steps, contact, footer, privacy notice, search
 * engines), rendered with the settings field partial. Permission: manage-landing.
 */
class WebsiteController extends Controller
{
    use SavesSettingsGroup;

    public function __construct(private readonly SettingsService $settings) {}

    public function edit(LandingContent $content): View
    {
        $this->authorize('manage-landing');

        return view('admin.website.content', [
            'fields'    => $this->settings->fields('landing'),
            'settings'  => $this->settings,
            'fallbacks' => $content->fallbacks(),
            'order'     => $content->sectionOrder(),
            'counts'    => self::tabCounts(),
        ]);
    }

    public function update(UpdateLandingSettingsRequest $request, LandingContent $content): RedirectResponse
    {
        $changed = $this->saveSettingsFields($request, $this->settings, 'landing', $this->settings->fields('landing'));

        LandingContent::forget();

        return redirect()
            ->route('admin.website.edit')
            ->with('success', $changed ? 'Website saved. The changes are live.' : 'No changes to save.');
    }

    /** Counts shown on the Website tabs. */
    public static function tabCounts(): array
    {
        $counts = LandingItem::query()->selectRaw('section, count(*) as n')->groupBy('section')->pluck('n', 'section');

        return [
            'service'    => (int) ($counts['service'] ?? 0),
            'faq'        => (int) ($counts['faq'] ?? 0),
            'team'       => (int) ($counts['team'] ?? 0),
            'advisories' => Announcement::query()->count(),
        ];
    }
}
