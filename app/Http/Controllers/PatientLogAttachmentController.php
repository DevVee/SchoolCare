<?php

namespace App\Http\Controllers;

use App\Models\PatientLog;
use App\Models\PatientLogAttachment;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Photos attached to a clinic visit (injury documentation).
 *
 * Medical images: stored on the private "local" disk (storage/app/private)
 * under hashed names, never on the public disk, and streamed only through
 * show() after a view-patient-logs check, with no-store caching.
 */
class PatientLogAttachmentController extends Controller
{
    private const DISK = 'local';

    public function store(Request $request, PatientLog $patientLog)
    {
        $this->authorize('update-patient-logs');

        $remaining = PatientLog::MAX_ATTACHMENTS - $patientLog->attachments()->count();

        $request->validate([
            'photos'   => ['required', 'array', 'min:1', 'max:'.max(0, $remaining)],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photos.max'     => $remaining > 0
                ? "You can attach {$remaining} more ".str('photo')->plural($remaining).' to this visit (up to '.PatientLog::MAX_ATTACHMENTS.').'
                : 'This visit already has the maximum of '.PatientLog::MAX_ATTACHMENTS.' photos.',
            'photos.*.mimes' => 'Photos must be JPG, PNG or WebP images.',
            'photos.*.max'   => 'Each photo must be 5 MB or smaller.',
        ]);

        $saved = 0;
        foreach ($request->file('photos', []) as $file) {
            // store() uses a random hashed file name; the original name is kept only as metadata.
            $path = $file->store("patient-logs/{$patientLog->id}", self::DISK);
            if (! $path) {
                continue;
            }

            $patientLog->attachments()->create([
                'disk'          => self::DISK,
                'path'          => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime_type'     => $file->getMimeType(),
                'size'          => (int) $file->getSize(),
                'uploaded_by'   => auth()->id(),
            ]);
            $saved++;
        }

        AuditLogService::log(
            action: 'created',
            module: 'patient_logs',
            description: "{$saved} ".str('photo')->plural($saved)." attached to clinic log #{$patientLog->id}",
        );

        return redirect()
            ->route('patient-logs.show', $patientLog)
            ->with('success', "{$saved} ".str('photo')->plural($saved).' attached.');
    }

    public function show(PatientLog $patientLog, PatientLogAttachment $attachment)
    {
        $this->authorize('view-patient-logs');

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        $name = 'visit-'.$patientLog->id.'-photo-'.$attachment->id.'.'.pathinfo($attachment->path, PATHINFO_EXTENSION);

        return $disk->response($attachment->path, $name, [
            'Content-Type'            => $attachment->mime_type ?: 'application/octet-stream',
            'Cache-Control'           => 'private, no-store, max-age=0',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
        ], request()->boolean('download') ? 'attachment' : 'inline');
    }

    public function destroy(PatientLog $patientLog, PatientLogAttachment $attachment)
    {
        $this->authorize('update-patient-logs');

        $attachment->delete(); // model event removes the file

        AuditLogService::log(
            action: 'deleted',
            module: 'patient_logs',
            description: "Photo #{$attachment->id} removed from clinic log #{$patientLog->id}",
        );

        return redirect()
            ->route('patient-logs.show', $patientLog)
            ->with('success', 'Photo removed.');
    }
}
