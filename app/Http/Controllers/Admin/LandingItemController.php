<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingItem;
use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Administration → Website: Services, Common questions and Clinic team tabs.
 * {segment} is services | faqs | team. Permission: manage-landing (route).
 * Images go to the public disk under landing/ with random (hashed) names.
 */
class LandingItemController extends Controller
{
    /** Icons offered for services (Bootstrap Icons names). */
    public const ICONS = [
        'bandaid'              => 'First aid',
        'clipboard2-pulse'     => 'Check-up',
        'capsule'              => 'Medicine',
        'prescription2'        => 'Prescription',
        'file-earmark-medical' => 'Health record',
        'calendar2-heart'      => 'Visit day',
        'heart-pulse'          => 'Heart and vital signs',
        'thermometer-half'     => 'Temperature',
        'lungs'                => 'Breathing',
        'eye'                  => 'Vision',
        'ear'                  => 'Hearing',
        'droplet-half'         => 'Blood',
        'shield-plus'          => 'Protection and vaccines',
        'clipboard-check'      => 'Clearance',
        'chat-heart'           => 'Counselling',
        'person-wheelchair'    => 'Accessibility',
        'hospital'             => 'Referral',
        'activity'             => 'Monitoring',
    ];

    public function index(string $segment): View
    {
        $section = $this->section($segment);

        return view('admin.website.items.index', [
            'section' => $section,
            'segment' => $segment,
            'meta'    => LandingItem::SECTIONS[$section],
            'items'   => LandingItem::query()->section($section)->ordered()->get(),
            'counts'  => WebsiteController::tabCounts(),
        ]);
    }

    public function create(string $segment): View
    {
        $section = $this->section($segment);

        return $this->form($segment, $section, new LandingItem(['section' => $section, 'is_enabled' => true]));
    }

    public function store(Request $request, string $segment): RedirectResponse
    {
        $section = $this->section($segment);
        $data = $this->validated($request, $section);

        $data['section'] = $section;
        $data['sort_order'] = (int) LandingItem::query()->section($section)->max('sort_order') + 1;
        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImage($request->file('image'));
        }

        $item = LandingItem::create($data);

        AuditLogService::log(
            action: 'created',
            module: 'website',
            description: 'Added '.LandingItem::SECTIONS[$section]['singular'].' "'.Str::limit($item->title, 80).'" to the website',
            newValues: $item->only(['section', 'title', 'subtitle', 'icon', 'image', 'link_url', 'is_enabled']),
        );

        return redirect()->route('admin.website.items.index', $segment)
            ->with('success', ucfirst(LandingItem::SECTIONS[$section]['singular']).' added.');
    }

    public function edit(string $segment, LandingItem $item): View
    {
        $section = $this->section($segment, $item);

        return $this->form($segment, $section, $item);
    }

    public function update(Request $request, string $segment, LandingItem $item): RedirectResponse
    {
        $section = $this->section($segment, $item);
        $data = $this->validated($request, $section);
        $old = $item->only(array_merge(array_keys($data), ['image']));
        $oldImage = $item->image;

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImage($request->file('image'));
        } elseif ($request->boolean('remove_image')) {
            $data['image'] = null;
        }

        $item->update($data);

        if ($oldImage && $oldImage !== $item->image) {
            $this->deleteImage($oldImage);
        }

        AuditLogService::log(
            action: 'updated',
            module: 'website',
            description: 'Updated '.LandingItem::SECTIONS[$section]['singular'].' "'.Str::limit($item->title, 80).'" on the website',
            oldValues: $old,
            newValues: $item->only(array_keys($old)),
        );

        return redirect()->route('admin.website.items.index', $segment)
            ->with('success', 'Changes saved. They are live on the website.');
    }

    public function toggle(string $segment, LandingItem $item): RedirectResponse
    {
        $section = $this->section($segment, $item);
        $item->update(['is_enabled' => ! $item->is_enabled]);

        AuditLogService::log(
            action: 'updated',
            module: 'website',
            description: ($item->is_enabled ? 'Showed' : 'Hid').' '.LandingItem::SECTIONS[$section]['singular'].' "'.Str::limit($item->title, 80).'" on the website',
        );

        return back()->with('success', $item->is_enabled ? 'Shown on the website.' : 'Hidden from the website.');
    }

    public function move(Request $request, string $segment, LandingItem $item): RedirectResponse
    {
        $section = $this->section($segment, $item);
        $direction = $request->input('direction') === 'up' ? -1 : 1;

        DB::transaction(function () use ($section, $item, $direction) {
            $ids = LandingItem::query()->section($section)->ordered()->pluck('id')->all();
            $pos = array_search($item->id, $ids, true);
            $swap = $pos + $direction;
            if ($pos === false || $swap < 0 || $swap >= count($ids)) {
                return;
            }
            [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
            foreach ($ids as $i => $id) {
                LandingItem::query()->whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        \App\Services\LandingContent::forget();

        AuditLogService::log(
            action: 'updated',
            module: 'website',
            description: 'Moved '.LandingItem::SECTIONS[$section]['singular'].' "'.Str::limit($item->title, 80).'" '.($direction < 0 ? 'up' : 'down'),
        );

        return back();
    }

    public function destroy(string $segment, LandingItem $item): RedirectResponse
    {
        $section = $this->section($segment, $item);
        $title = $item->title;
        $image = $item->image;

        $item->delete();
        if ($image) {
            $this->deleteImage($image);
        }

        AuditLogService::log(
            action: 'deleted',
            module: 'website',
            description: 'Deleted '.LandingItem::SECTIONS[$section]['singular'].' "'.Str::limit($title, 80).'" from the website',
        );

        return redirect()->route('admin.website.items.index', $segment)
            ->with('success', ucfirst(LandingItem::SECTIONS[$section]['singular']).' deleted.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Section key for the URL segment (404 when unknown or when the item belongs elsewhere). */
    private function section(string $segment, ?LandingItem $item = null): string
    {
        $section = LandingItem::sectionFromSegment($segment);
        abort_if($section === null || ($item && $item->section !== $section), 404);

        return $section;
    }

    private function form(string $segment, string $section, LandingItem $item): View
    {
        return view('admin.website.items.form', [
            'item'    => $item,
            'section' => $section,
            'segment' => $segment,
            'meta'    => LandingItem::SECTIONS[$section],
            'icons'   => self::ICONS,
        ]);
    }

    private function validated(Request $request, string $section): array
    {
        $image = ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', self::realImage()];

        $rules = match ($section) {
            'service' => [
                'title'      => ['required', 'string', 'max:150'],
                'body'       => ['required', 'string', 'max:1000'],
                'icon'       => ['nullable', 'string', 'in:'.implode(',', array_keys(self::ICONS))],
                'image'      => $image,
                'link_label' => ['nullable', 'string', 'max:60'],
                'link_url'   => ['nullable', 'url:http,https', 'max:300', 'required_with:link_label'],
            ],
            'faq' => [
                'title' => ['required', 'string', 'max:150'],
                'body'  => ['required', 'string', 'max:3000'],
            ],
            'team' => [
                'title'    => ['required', 'string', 'max:150'],
                'subtitle' => ['required', 'string', 'max:150'],
                'body'     => ['nullable', 'string', 'max:500'],
                'image'    => $image,
            ],
        };
        $rules['is_enabled'] = ['nullable', 'boolean'];
        $rules['remove_image'] = ['nullable', 'boolean'];

        $labels = match ($section) {
            'service' => ['title' => 'service name', 'body' => 'description', 'link_url' => 'link address'],
            'faq'     => ['title' => 'question', 'body' => 'answer'],
            'team'    => ['title' => 'name', 'subtitle' => 'role', 'body' => 'short note', 'image' => 'photo'],
        };

        $data = $request->validate($rules, [], $labels);
        unset($data['image'], $data['remove_image']);
        $data['is_enabled'] = $request->boolean('is_enabled');

        foreach (['subtitle', 'body', 'icon', 'link_label', 'link_url'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = filled($data[$key]) ? trim((string) $data[$key]) : null;
            }
        }

        return $data;
    }

    /** Sniff the real file content: an SVG or HTML file renamed to .png is rejected. */
    public static function realImage(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! $value instanceof UploadedFile || ! $value->isValid()) {
                return;
            }
            $mime = @(new \finfo(FILEINFO_MIME_TYPE))->file($value->getRealPath());
            if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                $fail('The :attribute must be a PNG, JPG or WebP image.');
            }
        };
    }

    private function storeImage(UploadedFile $file): string
    {
        return $file->store('landing', 'public');   // random 40-character name
    }

    private function deleteImage(string $path): void
    {
        if (str_starts_with($path, 'landing/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
