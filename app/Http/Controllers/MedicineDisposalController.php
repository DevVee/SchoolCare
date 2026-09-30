<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineDisposal;
use App\Services\AuditLogService;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Dispose of expired / damaged batches and review the disposal history. */
class MedicineDisposalController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function store(Request $request, MedicineBatch $batch)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $disposal = $this->inventory->disposeBatch($batch, trim($data['reason']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $medicine = $disposal->medicine;

        return back()->with('success', "Disposed {$disposal->quantity} {$medicine?->unit}(s) of \"{$medicine?->name}\"."
            .' The batch is now in the disposal history.');
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $base = $this->query($filters);

        $disposals = (clone $base)
            ->with(['medicine', 'disposedBy'])
            ->latest('disposed_at')
            ->paginate(25)
            ->withQueryString();

        $totals = [
            'count'    => (clone $base)->count(),
            'quantity' => (int) (clone $base)->sum('quantity'),
            'cost'     => (float) (clone $base)->sum('total_cost'),
        ];

        $medicines = Medicine::withTrashed()
            ->whereIn('id', MedicineDisposal::select('medicine_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $years = MedicineDisposal::query()->pluck('disposed_at')
            ->map(fn ($d) => $d->year)->unique()->sortDesc()->values();

        return view('inventory.disposals', compact('disposals', 'filters', 'totals', 'medicines', 'years'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows    = $this->query($filters)->with(['medicine', 'disposedBy'])->latest('disposed_at');

        AuditLogService::log(action: 'exported', module: 'inventory', description: 'Exported medicine disposal history (CSV)');

        $name = 'disposal-history-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, ['Disposed at', 'Medicine', 'Batch no.', 'Expiry', 'Quantity', 'Unit', 'Unit cost', 'Total cost', 'Reason', 'Disposed by']);

            $rows->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $d) {
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $d->disposed_at?->format('Y-m-d H:i'),
                        $d->medicine?->name,
                        $d->batch_number,
                        $d->expiry_date?->format('Y-m-d'),
                        $d->quantity,
                        $d->medicine?->unit,
                        $d->unit_cost,
                        $d->total_cost,
                        $d->reason,
                        $d->disposedBy?->name,
                    ]));
                }
            });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formulas in user-entered text. */
    public function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function filters(Request $request): array
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';

        return [
            'medicine_id' => $request->integer('medicine_id') ?: '',
            'year'        => $request->integer('year') ?: '',
            'month'       => in_array($request->integer('month'), range(1, 12), true) ? $request->integer('month') : '',
            'date_from'   => $date($request->get('date_from')),
            'date_to'     => $date($request->get('date_to')),
            'search'      => trim((string) $request->get('search', '')),
        ];
    }

    private function query(array $f): Builder
    {
        return MedicineDisposal::query()
            ->when($f['medicine_id'], fn ($q) => $q->where('medicine_id', $f['medicine_id']))
            ->when($f['year'], fn ($q) => $q->whereYear('disposed_at', $f['year']))
            ->when($f['month'], fn ($q) => $q->whereMonth('disposed_at', $f['month']))
            ->when($f['date_from'], fn ($q) => $q->whereDate('disposed_at', '>=', $f['date_from']))
            ->when($f['date_to'], fn ($q) => $q->whereDate('disposed_at', '<=', $f['date_to']))
            ->when($f['search'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('batch_number', 'like', "%{$f['search']}%")
                ->orWhere('reason', 'like', "%{$f['search']}%")
                ->orWhereHas('medicine', fn ($m) => $m->where('name', 'like', "%{$f['search']}%"))));
    }
}
