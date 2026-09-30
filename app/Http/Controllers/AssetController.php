<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Clinic equipment and supplies (SSCMS asset inventory). */
class AssetController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-assets');

        $filters = $this->filters($request);

        $assets = $this->query($filters)
            ->orderBy($filters['sort'] === 'recent' ? 'created_at' : 'name', $filters['sort'] === 'recent' ? 'desc' : 'asc')
            ->paginate(20)
            ->withQueryString();

        $categories = Asset::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        $summary = [
            'items'    => Asset::count(),
            'units'    => (int) Asset::sum('quantity'),
            'value'    => (float) Asset::selectRaw('COALESCE(SUM(cost * quantity), 0) as v')->value('v'),
            'attention'=> Asset::whereIn('condition', array_values(array_diff(Asset::conditions(), [Asset::conditions()[0] ?? 'Good'])))->count(),
        ];

        return view('assets.index', compact('assets', 'filters', 'categories', 'summary'));
    }

    public function create()
    {
        $this->authorize('manage-assets');

        return view('assets.create', [
            'asset'      => new Asset(['quantity' => 1, 'condition' => Asset::conditions()[0] ?? null]),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-assets');

        $data = $this->validated($request);
        $data['created_by'] = $data['updated_by'] = auth()->id();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('assets', 'public');
        }

        $asset = Asset::create($data);

        AuditLogService::log(
            action: 'created',
            module: 'assets',
            description: "Asset \"{$asset->name}\" added (qty {$asset->quantity}, {$asset->condition})",
            newValues: $asset->only(['name', 'category', 'property_number', 'quantity', 'condition', 'location', 'cost']),
        );

        return redirect()->route('assets.show', $asset)->with('success', "Asset \"{$asset->name}\" added.");
    }

    public function show(Asset $asset)
    {
        $this->authorize('view-assets');

        $asset->load('createdBy', 'updatedBy');

        return view('assets.show', compact('asset'));
    }

    public function edit(Asset $asset)
    {
        $this->authorize('manage-assets');

        return view('assets.edit', [
            'asset'      => $asset,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        $this->authorize('manage-assets');

        $data = $this->validated($request, $asset);
        $data['updated_by'] = auth()->id();

        $oldImage = $asset->image_path;
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('assets', 'public');
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }

        $old = $asset->only(['name', 'category', 'property_number', 'quantity', 'condition', 'location', 'cost']);
        $asset->update($data);

        if ($oldImage && $asset->image_path !== $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        AuditLogService::log(
            action: 'updated',
            module: 'assets',
            description: "Asset \"{$asset->name}\" updated",
            oldValues: $old,
            newValues: $asset->only(array_keys($old)),
        );

        return redirect()->route('assets.show', $asset)->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset)
    {
        $this->authorize('manage-assets');

        $asset->delete(); // soft delete; the image is kept with the record

        AuditLogService::log(
            action: 'deleted',
            module: 'assets',
            description: "Asset \"{$asset->name}\" removed",
        );

        return redirect()->route('assets.index')->with('success', "Asset \"{$asset->name}\" removed.");
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows    = $this->query($filters)->orderBy('name');

        AuditLogService::log(action: 'exported', module: 'assets', description: 'Exported asset list (CSV)');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'Category', 'Property / serial no.', 'Quantity', 'Condition', 'Location', 'Acquired', 'Unit cost', 'Notes']);
            $rows->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $a) {
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $a->name, $a->category, $a->property_number, $a->quantity, $a->condition,
                        $a->location, $a->acquired_at?->format('Y-m-d'), $a->cost, $a->notes,
                    ]));
                }
            });
            fclose($out);
        }, 'assets-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function validated(Request $request, ?Asset $asset = null): array
    {
        $conditions = Asset::conditions();
        if ($asset?->condition) {
            $conditions[] = $asset->condition; // keep a condition removed from Settings valid
        }

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:200'],
            'category'        => ['nullable', 'string', 'max:100'],
            'property_number' => ['nullable', 'string', 'max:100'],
            'quantity'        => ['required', 'integer', 'min:0', 'max:100000'],
            'condition'       => ['required', 'string', Rule::in($conditions)],
            'location'        => ['nullable', 'string', 'max:150'],
            'acquired_at'     => ['nullable', 'date', 'before_or_equal:today'],
            'cost'            => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'image'           => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image'    => ['nullable', 'boolean'],
        ], [
            'image.mimes' => 'The picture must be a JPG, PNG or WebP image.',
            'image.max'   => 'The picture must be 2 MB or smaller.',
        ], [
            'property_number' => 'property / serial number',
            'acquired_at'     => 'date acquired',
        ]);

        unset($data['image'], $data['remove_image']);

        return $data;
    }

    private function filters(Request $request): array
    {
        $conditions = Asset::conditions();
        $condition  = (string) $request->get('condition', '');

        return [
            'search'    => trim((string) $request->get('search', '')),
            'condition' => in_array($condition, $conditions, true) ? $condition : '',
            'category'  => trim((string) $request->get('category', '')),
            'sort'      => $request->get('sort') === 'recent' ? 'recent' : 'name',
        ];
    }

    private function query(array $f): Builder
    {
        return Asset::query()
            ->search($f['search'])
            ->when($f['condition'] !== '', fn ($q) => $q->where('condition', $f['condition']))
            ->when($f['category'] !== '', fn ($q) => $q->where('category', $f['category']));
    }

    private function categoryOptions()
    {
        return Asset::withTrashed()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
    }
}
