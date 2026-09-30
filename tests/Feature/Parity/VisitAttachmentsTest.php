<?php

namespace Tests\Feature\Parity;

use App\Models\PatientLog;
use App\Models\PatientLogAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Clinical\ClinicalTestCase;

class VisitAttachmentsTest extends ClinicalTestCase
{
    public function test_photos_are_stored_privately_and_served_only_to_authorized_users(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $log = PatientLog::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('patient-logs.attachments.store', $log), [
                'photos' => [UploadedFile::fake()->image('knee-wound.jpg', 800, 600)],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('patient-logs.show', $log));

        $photo = PatientLogAttachment::firstOrFail();
        $this->assertSame('local', $photo->disk);
        $this->assertStringStartsWith("patient-logs/{$log->id}/", $photo->path);
        $this->assertStringNotContainsString('knee-wound', $photo->path);
        Storage::disk('local')->assertExists($photo->path);
        Storage::disk('public')->assertMissing($photo->path);

        $url = route('patient-logs.attachments.show', [$log, $photo]);

        // Authorized staff can view it, never cached.
        $res = $this->actingAs($this->admin)->get($url);
        $res->assertOk();
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));

        // A role without view-patient-logs is refused.
        $noLogs = $this->userWithRole('staff');
        $noLogs->roles()->first()->revokePermissionTo('view-patient-logs');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($noLogs->fresh())->get($url)->assertForbidden();

        // Guests are sent to login.
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));

        // The private disk has no public URL: the raw storage path needs a signature.
        $this->get('/storage/'.$photo->path)->assertStatus(403);
    }

    public function test_photo_must_belong_to_the_visit_in_the_url(): void
    {
        Storage::fake('local');
        $log   = PatientLog::factory()->create();
        $other = PatientLog::factory()->create();

        $this->actingAs($this->admin)->post(route('patient-logs.attachments.store', $other), [
            'photos' => [UploadedFile::fake()->image('a.png')],
        ]);
        $photo = PatientLogAttachment::firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('patient-logs.attachments.show', [$log, $photo]))
            ->assertNotFound();
    }

    public function test_upload_and_delete_need_update_permission_limits_apply_and_delete_removes_file(): void
    {
        Storage::fake('local');
        $log = PatientLog::factory()->create();

        // staff: view only
        $this->actingAs($this->userWithRole('staff'))
            ->post(route('patient-logs.attachments.store', $log), ['photos' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertForbidden();

        // Only images.
        $this->actingAs($this->admin)
            ->post(route('patient-logs.attachments.store', $log), ['photos' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]])
            ->assertSessionHasErrors('photos.0');

        // Max 5 per visit.
        $six = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 6));
        $this->actingAs($this->admin)
            ->post(route('patient-logs.attachments.store', $log), ['photos' => $six])
            ->assertSessionHasErrors('photos');
        $this->assertSame(0, $log->attachments()->count());

        $this->actingAs($this->admin)
            ->post(route('patient-logs.attachments.store', $log), ['photos' => array_slice($six, 0, 5)])
            ->assertSessionHasNoErrors();
        $this->assertSame(5, $log->attachments()->count());

        $this->actingAs($this->admin)
            ->post(route('patient-logs.attachments.store', $log), ['photos' => [UploadedFile::fake()->image('extra.jpg')]])
            ->assertSessionHasErrors('photos');

        $photo = $log->attachments()->first();
        $this->actingAs($this->userWithRole('staff'))
            ->delete(route('patient-logs.attachments.destroy', [$log, $photo]))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('nurse'))
            ->delete(route('patient-logs.attachments.destroy', [$log, $photo]))
            ->assertRedirect(route('patient-logs.show', $log));

        $this->assertModelMissing($photo);
        Storage::disk('local')->assertMissing($photo->path);
    }

    public function test_barcode_lookup_finds_medicines(): void
    {
        $m = \App\Models\Medicine::factory()->create(['barcode' => '4801234567890']);

        $this->actingAs($this->admin)->getJson(route('medicines.lookup', ['barcode' => '4801234567890']))
            ->assertOk()->assertJsonPath('found', true)->assertJsonPath('medicine.id', $m->id);

        $this->actingAs($this->admin)->getJson(route('medicines.lookup', ['barcode' => '000']))
            ->assertNotFound()->assertJsonPath('found', false);

        $this->actingAs($this->userWithRole('staff'))->getJson(route('medicines.lookup', ['barcode' => '4801234567890']))
            ->assertForbidden();
    }
}
