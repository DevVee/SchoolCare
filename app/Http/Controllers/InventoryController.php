<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StockInRequest;
use App\Http\Requests\Inventory\StockOutRequest;
use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $service) {}

    /* ------------------------------------------------------------------ */
    /*  INDEX — medicines with current stock                               */
    /* ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $this->authorize('view-inventory');

        $search   = trim((string) $request->get('search', ''));
        $category = $request->get('category', '');
        $status   = $request->get('status', 'active');   // active | inactive | all
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $medicines = Medicine::with('category')
            ->search($search)
            ->when($category, fn ($q) => $q->where('category_id', $category))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $categories = \App\Models\MedicineCategory::orderBy('name')->get();

        return view('inventory.index', compact('medicines', 'categories', 'search', 'category', 'status'));
    }

    /* ------------------------------------------------------------------ */
    /*  STOCK IN                                                            */
    /* ------------------------------------------------------------------ */
    public function stockInForm(Request $request)
    {
        $this->authorize('manage-inventory');

        $medicines = Medicine::active()->orderBy('name')->get();
        $selected  = $request->integer('medicine_id') ?: null;

        return view('inventory.stock-in', compact('medicines', 'selected'));
    }

    public function stockIn(StockInRequest $request)
    {
        $medicine = Medicine::findOrFail($request->medicine_id);
        $this->service->stockIn($medicine, $request->validated());

        return redirect()
            ->route('inventory.index')
            ->with('success', "Stock added: +{$request->quantity} {$medicine->unit}(s) of \"{$medicine->name}\".");
    }

    /* ------------------------------------------------------------------ */
    /*  STOCK OUT                                                           */
    /* ------------------------------------------------------------------ */
    public function stockOutForm(Request $request)
    {
        $this->authorize('manage-inventory');

        // Inactive medicines are included so withdrawn stock can still be
        // written off. Expired batches are listed but leave through Dispose.
        $medicines = Medicine::where('quantity', '>', 0)
            ->with(['batches' => fn ($q) => $q->inStock()->fefo()])
            ->orderByDesc('is_active')->orderBy('name')->get();
        $selected  = $request->integer('medicine_id') ?: null;

        return view('inventory.stock-out', compact('medicines', 'selected'));
    }

    public function stockOut(StockOutRequest $request)
    {
        $medicine = Medicine::findOrFail($request->medicine_id);

        try {
            $this->service->stockOut($medicine, $request->validated());
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()
            ->route('inventory.index')
            ->with('success', "Stock removed: -{$request->quantity} {$medicine->unit}(s) of \"{$medicine->name}\".");
    }

    /* ------------------------------------------------------------------ */
    /*  TRANSACTIONS LEDGER                                                 */
    /* ------------------------------------------------------------------ */
    public function transactions(Request $request)
    {
        $this->authorize('view-inventory');

        $search   = trim((string) $request->get('search', ''));
        $type     = $request->get('type', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo   = $request->get('date_to', '');

        $transactions = InventoryTransaction::with(['medicine', 'performedBy'])
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('medicine', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
                ->orWhere('batch_number', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%")))
            ->when($type,     fn ($q) => $q->where('transaction_type', $type))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $filters = compact('search', 'type', 'dateFrom', 'dateTo');

        return view('inventory.transactions', compact('transactions', 'filters'));
    }
}
