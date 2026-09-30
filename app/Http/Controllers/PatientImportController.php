<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\Patients\PatientColumns;
use App\Services\Patients\PatientExportService;
use App\Services\Patients\PatientImportService;
use Illuminate\Http\Request;

/**
 * Patient import from .xlsx / .csv: upload, preview with per-row errors,
 * confirm. The uploaded file is kept privately between preview and confirm
 * and is deleted afterwards.
 */
class PatientImportController extends Controller
{
    private const SESSION_KEY = 'patient_import';

    public function __construct(private readonly PatientImportService $importer) {}

    public function create(Request $request)
    {
        $this->authorize('import-patients');

        return view('patients.import.create', [
            'columns'        => PatientColumns::COLUMNS,
            'required'       => PatientColumns::REQUIRED,
            'categoryLabels' => Patient::categoryLabels(),
            'sexLabels'      => Patient::sexLabels(),
            'maxRows'        => PatientImportService::MAX_ROWS,
        ]);
    }

    public function template(Request $request, PatientExportService $export)
    {
        $this->authorize('import-patients');

        return $export->template($request->query('format') === 'csv' ? 'csv' : 'xlsx');
    }

    /** Upload and show the preview. */
    public function preview(Request $request)
    {
        $this->authorize('import-patients');

        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,xlsx', 'max:'.PatientImportService::MAX_KB],
        ], [
            'file.extensions' => 'Upload an Excel (.xlsx) or CSV (.csv) file.',
            'file.max'        => 'The file is larger than 5 MB. Split it into smaller files.',
        ]);

        $file = $request->file('file');
        if (! PatientImportService::looksValid($file)) {
            return back()->withErrors(['file' => 'This file could not be read as an Excel or CSV file.']);
        }

        // Replace any earlier upload of this user.
        if ($old = $request->session()->get(self::SESSION_KEY)) {
            $this->importer->discard($old['token'] ?? '');
        }

        $name  = $file->getClientOriginalName();
        $token = $this->importer->store($file);
        $path  = $this->importer->path($token);

        try {
            $analysis = $this->importer->analyze($path);
        } catch (\Throwable $e) {
            report($e);
            $this->importer->discard($token);

            return back()->withErrors(['file' => 'This file could not be read. Save it again as .xlsx or .csv (UTF-8) and retry.']);
        }

        $request->session()->put(self::SESSION_KEY, ['token' => $token, 'name' => $name]);

        return view('patients.import.preview', [
            'analysis'       => $analysis,
            'token'          => $token,
            'filename'       => $name,
            'categoryLabels' => Patient::categoryLabels(),
            'columns'        => PatientColumns::COLUMNS,
        ]);
    }

    /** Import the valid rows of the previewed file. */
    public function store(Request $request)
    {
        $this->authorize('import-patients');

        $request->validate(['token' => ['required', 'string', 'size:40']]);

        $session = $request->session()->get(self::SESSION_KEY);
        $token   = (string) $request->input('token');
        $path    = $this->importer->path($token);

        if (! $session || ($session['token'] ?? null) !== $token || ! $path) {
            return redirect()->route('patients.import.create')
                ->with('error', 'The uploaded file has expired. Please upload it again.');
        }

        $result = $this->importer->import($path, $session['name'] ?? 'upload');

        $this->importer->discard($token);
        $request->session()->forget(self::SESSION_KEY);

        $message = "Imported {$result['imported']} patient(s)."
            .($result['skipped'] ? " Skipped {$result['skipped']} row(s) with errors or duplicates." : '')
            .($result['first'] ? " Patient numbers {$result['first']} to {$result['last']}." : '');

        return redirect()
            ->route('patients.index', ['sort' => 'created_at', 'dir' => 'desc'])
            ->with($result['imported'] ? 'success' : 'error', $message);
    }

    /** Cancel a previewed import (deletes the stored file). */
    public function destroy(Request $request)
    {
        $this->authorize('import-patients');

        if ($session = $request->session()->pull(self::SESSION_KEY)) {
            $this->importer->discard($session['token'] ?? '');
        }

        return redirect()->route('patients.import.create')->with('success', 'Import cancelled. Nothing was imported.');
    }
}
