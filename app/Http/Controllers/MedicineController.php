<?php

namespace App\Http\Controllers;

use App\Http\Requests\Medicine\StoreMedicineRequest;
use App\Http\Requests\Medicine\UpdateMedicineRequest;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicineController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /* ------------------------------------------------------------------ */
    /*  INDEX                                                               */
    /* ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $this->authorize('view-medicines');

        $search      = trim((string) $request->get('search', ''));
        $category    = $request->get('category', '');
        $stockFilter = $request->get('stock', '');        // low | out | expiring | expired
        $status      = $request->get('status', 'all');    // active | inactive | all
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'all';
        }

        // Inactive medicines stay visible here (with a badge) so they can be
        // found and re-activated; only selection dropdowns exclude them.
        $medicines = Medicine::with('category')
            ->search($search)
            ->when($category, fn ($q) => $q->where('category_id', $category))
            ->when($stockFilter === 'low', fn ($q) => $q->lowStock())
            ->when($stockFilter === 'out', fn ($q) => $q->where('quantity', 0))
            ->when($stockFilter === 'expiring', fn ($q) => $q->expiringSoon())
            ->when($stockFilter === 'expired', fn ($q) => $q->expired())
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $categories = MedicineCategory::orderBy('name')->get();

        // Summary cards (low stock excludes out of stock, which has its own card).
        $stats = [
            'total'    => Medicine::count(),
            'low'      => Medicine::lowStock()->where('quantity', '>', 0)->count(),
            'expiring' => Medicine::expiringSoon()->count(),
            'out'      => Medicine::where('quantity', 0)->count(),
        ];

        return view('medicines.index', [
            'medicines'   => $medicines,
            'categories'  => $categories,
            'filters'     => compact('search', 'category', 'stockFilter', 'status'),
            'stats'       => $stats,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  CREATE / STORE                                                      */
    /* ------------------------------------------------------------------ */
    public function create()
    {
        $this->authorize('create-medicines');

        $categories = MedicineCategory::orderBy('name')->get();

        return view('medicines.create', compact('categories'));
    }

    public function store(StoreMedicineRequest $request)
    {
        $data               = $request->validated();
        $data['created_by'] = auth()->id();
        $data['is_active']  = $request->boolean('is_active', true);

        // Opening stock goes through the ledger as a stock-in so the
        // inventory transactions always reconcile with the quantity.
        $opening          = (int) ($data['quantity'] ?? 0);
        $data['quantity'] = 0;

        $medicine = DB::transaction(function () use ($data, $opening) {
            $medicine = Medicine::create($data);

            if ($opening > 0) {
                $this->inventory->stockIn($medicine, [
                    'quantity'        => $opening,
                    'batch_number'    => $data['batch_number'] ?? null,
                    'expiration_date' => $data['expiration_date'] ?? null,
                    'supplier'        => $data['supplier'] ?? null,
                    'unit_cost'       => $data['purchase_price'] ?? null,
                    'notes'           => 'Opening stock (medicine created)',
                ]);
            }

            return $medicine->fresh();
        });

        return redirect()
            ->route('medicines.show', $medicine)
            ->with('success', 'Medicine "' . $medicine->name . '" added successfully.');
    }

    /* ------------------------------------------------------------------ */
    /*  SHOW                                                                */
    /* ------------------------------------------------------------------ */
    public function show(Medicine $medicine)
    {
        $this->authorize('view-medicines');

        $medicine->load('category', 'createdBy');

        $transactions = $medicine->inventoryTransactions()
            ->with('performedBy', 'batch')
            ->latest()
            ->limit(10)
            ->get();

        // Stock on hand first (FEFO order), then used-up and disposed lots.
        $batches = $medicine->batches()
            ->with('createdBy')
            ->orderByRaw('CASE WHEN disposed_at IS NULL AND quantity > 0 THEN 0 ELSE 1 END')
            ->fefo()
            ->limit(100)
            ->get();

        return view('medicines.show', compact('medicine', 'transactions', 'batches'));
    }

    /* ------------------------------------------------------------------ */
    /*  EDIT / UPDATE                                                       */
    /* ------------------------------------------------------------------ */
    public function edit(Medicine $medicine)
    {
        $this->authorize('update-medicines');

        $categories = MedicineCategory::orderBy('name')->get();

        return view('medicines.edit', compact('medicine', 'categories'));
    }

    public function update(UpdateMedicineRequest $request, Medicine $medicine)
    {
        $this->authorize('update-medicines');

        $data              = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        // Stock, expiry and batch number only change through the batch ledger
        // (stock-in / stock-out / dispensing / disposal / batch corrections).
        unset($data['quantity'], $data['expiration_date'], $data['batch_number']);

        $medicine->update($data);

        return redirect()
            ->route('medicines.show', $medicine)
            ->with('success', 'Medicine "' . $medicine->name . '" updated successfully.');
    }

    /* ------------------------------------------------------------------ */
    /*  DESTROY                                                             */
    /* ------------------------------------------------------------------ */
    public function destroy(Medicine $medicine)
    {
        $this->authorize('delete-medicines');

        $medicine->delete();

        return redirect()
            ->route('medicines.index')
            ->with('success', 'Medicine removed from inventory.');
    }

    /* ------------------------------------------------------------------ */
    /*  LOW STOCK                                                           */
    /* ------------------------------------------------------------------ */
    public function lowStock()
    {
        $this->authorize('view-medicines');

        $medicines = Medicine::with('category')
            ->active()->lowStock()
            ->orderBy('quantity')
            ->paginate(20)
            ->withQueryString();

        return view('medicines.low-stock', compact('medicines'));
    }

    /* ------------------------------------------------------------------ */
    /*  EXPIRY: expired and expiring batches (dispose from here)          */
    /* ------------------------------------------------------------------ */
    public function expiring(Request $request)
    {
        $this->authorize('view-medicines');

        $default = Medicine::expiryWarningDays();
        $days    = $request->integer('days', $default);
        $days    = in_array($days, [7, 14, 30, 60, 90, 180, $default], true) ? $days : $default;
        $view    = in_array($request->get('view'), ['expired', 'expiring', 'all'], true) ? $request->get('view') : 'all';
        $search  = trim((string) $request->get('search', ''));

        $base = MedicineBatch::query()
            ->inStock()
            ->whereNotNull('expiry_date')
            ->whereHas('medicine', fn ($m) => $m->search($search));

        $batches = (clone $base)
            ->with('medicine.category')
            ->when($view === 'expired', fn ($q) => $q->whereDate('expiry_date', '<', today()))
            ->when($view === 'expiring', fn ($q) => $q->whereDate('expiry_date', '>=', today())
                                                      ->whereDate('expiry_date', '<=', today()->addDays($days)))
            ->when($view === 'all', fn ($q) => $q->whereDate('expiry_date', '<=', today()->addDays($days)))
            ->orderBy('expiry_date')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'expired'  => (clone $base)->whereDate('expiry_date', '<', today())->count(),
            'expiring' => (clone $base)->whereDate('expiry_date', '>=', today())
                                       ->whereDate('expiry_date', '<=', today()->addDays($days))->count(),
        ];

        return view('medicines.expiring', compact('batches', 'days', 'view', 'search', 'counts'));
    }

    /* ------------------------------------------------------------------ */
    /*  BARCODE LOOKUP (keyboard-wedge scanners)                           */
    /* ------------------------------------------------------------------ */
    public function lookup(Request $request)
    {
        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '' || mb_strlen($barcode) > 100) {
            return response()->json(['found' => false, 'message' => 'Enter or scan a barcode.'], 422);
        }

        $medicine = Medicine::where('barcode', $barcode)->first();
        if (! $medicine) {
            return response()->json(['found' => false, 'message' => "No medicine has barcode {$barcode}."], 404);
        }

        return response()->json([
            'found'    => true,
            'medicine' => [
                'id'             => $medicine->id,
                'name'           => $medicine->name,
                'generic_name'   => $medicine->generic_name,
                'unit'           => $medicine->unit,
                'quantity'       => $medicine->quantity,
                'purchase_price' => $medicine->purchase_price,
                'is_active'      => $medicine->is_active,
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  BATCH CORRECTION (batch no., expiry, cost, supplier)               */
    /* ------------------------------------------------------------------ */
    public function updateBatch(Request $request, Medicine $medicine, MedicineBatch $batch)
    {
        $data = $request->validate([
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date'  => ['nullable', 'date'],
            'unit_cost'    => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'supplier'     => ['nullable', 'string', 'max:200'],
        ]);

        if ($batch->is_disposed) {
            return back()->with('error', 'A disposed batch cannot be changed.');
        }

        $this->inventory->updateBatch($batch, $data);

        return redirect()
            ->route('medicines.show', $medicine)
            ->with('success', 'Batch details updated.');
    }
}
