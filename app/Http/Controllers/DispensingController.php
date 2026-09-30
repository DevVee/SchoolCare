<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dispensing\StoreDispensingRequest;
use App\Models\Consultation;
use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\Patient;
use App\Services\DispensingService;
use Illuminate\Http\Request;

class DispensingController extends Controller
{
    public function __construct(private readonly DispensingService $service) {}

    /* ------------------------------------------------------------------ */
    /*  INDEX                                                               */
    /* ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $this->authorize('view-dispensing');

        $search     = trim((string) $request->get('search', ''));
        $dateFrom   = $request->get('date_from', '');
        $dateTo     = $request->get('date_to', '');
        $medicineId = $request->integer('medicine_id') ?: '';

        // Search is grouped in one where() so it never bypasses the date /
        // medicine filters; matches patient name/number or medicine name.
        $records = DispensingRecord::with('patient', 'medicine.category', 'dispensedBy')
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('patient', fn ($p) => $p->withTrashed()->where(fn ($p2) => $p2
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('patient_number', 'like', "%{$search}%")))
                ->orWhereHas('medicine', fn ($m) => $m->withTrashed()->where('name', 'like', "%{$search}%"))
            ))
            ->when($medicineId, fn ($q) => $q->where('medicine_id', $medicineId))
            ->when($dateFrom, fn ($q) => $q->whereDate('dispensed_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->whereDate('dispensed_at', '<=', $dateTo))
            ->latest('dispensed_at')
            ->paginate(20)
            ->withQueryString();

        $filters   = compact('search', 'dateFrom', 'dateTo', 'medicineId');
        $medicines = Medicine::withTrashed()->orderBy('name')->get(['id', 'name']);

        return view('dispensing.index', compact('records', 'filters', 'medicines'));
    }

    /* ------------------------------------------------------------------ */
    /*  CREATE / STORE                                                      */
    /* ------------------------------------------------------------------ */
    public function create(Request $request)
    {
        $this->authorize('create-dispensing');

        $patients = Patient::active()
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'patient_number']);

        // Only active, unexpired, in-stock medicines can be selected.
        // usable_quantity / next_expiry: unexpired batches only (FEFO pool).
        $medicines = Medicine::with('category')
            ->dispensable()
            ->withSum(['batches as usable_quantity' => fn ($q) => $q->usable()], 'quantity')
            ->withMin(['batches as next_expiry' => fn ($q) => $q->usable()], 'expiry_date')
            ->orderBy('name')
            ->get(['id', 'name', 'generic_name', 'quantity', 'unit', 'category_id', 'low_stock_threshold', 'expiration_date']);

        $consultations = Consultation::whereDate('visit_date', '>=', now()->subDays(30))
            ->orderByDesc('visit_date')
            ->get(['id', 'patient_id', 'visit_date', 'chief_complaint']);

        $selectedPatient = $request->integer('patient_id') ?: null;

        return view('dispensing.create', compact('patients', 'medicines', 'consultations', 'selectedPatient'));
    }

    public function store(StoreDispensingRequest $request)
    {
        try {
            $record = $this->service->dispense($request->validated());
        } catch (\RuntimeException $e) {
            $field = str_contains($e->getMessage(), 'Insufficient stock') ? 'quantity' : 'medicine_id';

            return back()->withInput()->withErrors([$field => $e->getMessage()]);
        }

        return redirect()
            ->route('dispensing.show', $record)
            ->with('success', 'Medicine dispensed successfully.');
    }

    /* ------------------------------------------------------------------ */
    /*  SHOW                                                                */
    /* ------------------------------------------------------------------ */
    public function show(DispensingRecord $dispensing)
    {
        $this->authorize('view-dispensing');

        $dispensing->load('patient', 'medicine.category', 'dispensedBy', 'consultation', 'patientLog', 'transactions');

        return view('dispensing.show', compact('dispensing'));
    }
}
