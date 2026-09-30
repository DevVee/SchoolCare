<?php

namespace App\Http\Controllers;

use App\Models\MedicineCategory;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class MedicineCategoryController extends Controller
{
    public function index()
    {
        $this->authorize('view-medicines');

        $categories = MedicineCategory::withCount('medicines')
            ->orderBy('name')
            ->get();

        return view('medicine-categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-medicines');

        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:medicine_categories,name',
            'description' => 'nullable|string|max:255',
        ]);

        $category = MedicineCategory::create($data);
        AuditLogService::log('created', 'medicines', "Medicine category '{$category->name}' created");

        return redirect()
            ->route('medicine-categories.index')
            ->with('success', 'Category "' . $data['name'] . '" created.');
    }

    public function edit(MedicineCategory $medicineCategory)
    {
        $this->authorize('update-medicines');

        return view('medicine-categories.edit', compact('medicineCategory'));
    }

    public function update(Request $request, MedicineCategory $medicineCategory)
    {
        $this->authorize('update-medicines');

        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:medicine_categories,name,' . $medicineCategory->id,
            'description' => 'nullable|string|max:255',
        ]);

        $oldName = $medicineCategory->name;
        $medicineCategory->update($data);
        AuditLogService::log('updated', 'medicines', "Medicine category '{$oldName}' updated", ['name' => $oldName], ['name' => $medicineCategory->name]);

        return redirect()
            ->route('medicine-categories.index')
            ->with('success', 'Category updated.');
    }

    public function destroy(MedicineCategory $medicineCategory)
    {
        $this->authorize('delete-medicines');

        // Include removed (soft-deleted) medicines: their history still references the category.
        if ($medicineCategory->medicines()->withTrashed()->count() > 0) {
            return back()->with('error', 'Cannot delete "' . $medicineCategory->name . '". It still has medicines in it. Move them to another category first.');
        }

        $name = $medicineCategory->name;
        $medicineCategory->delete();
        AuditLogService::log('deleted', 'medicines', "Medicine category '{$name}' deleted");

        return redirect()
            ->route('medicine-categories.index')
            ->with('success', 'Category "' . $name . '" deleted.');
    }
}
