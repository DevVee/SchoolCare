<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Services\Patients\HealthReportService;
use App\Services\ReportService;
use App\Support\PrintBranding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Clinical\ClinicalTestCase;
use Tests\Feature\Clinical\InspectsPdf;

/**
 * Admin > Settings > Printing: letterhead banner, signatures and footer, and how
 * the report PDFs, the health record PDF and their browser print pages use them.
 */
class PrintBrandingTest extends ClinicalTestCase
{
    use InspectsPdf;

    /** @var list<string> temporary image files made by the tests */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    // ─── Settings page ───────────────────────────────────────────────────────

    public function test_printing_page_renders_in_the_settings_nav(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.edit', 'printing'))
            ->assertOk()
            ->assertSee('Letterhead')
            ->assertSee('Signatory 3')
            ->assertSee('Footer text')
            ->assertSee('data-print-settings', false)
            ->assertSee(route('admin.settings.edit', 'printing'));

        $this->actingAs($this->admin)
            ->get(route('admin.settings.edit', 'general'))
            ->assertSee(route('admin.settings.edit', 'printing'));
    }

    public function test_banner_upload_is_resized_keeps_transparency_and_is_audited(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'printing'), $this->payload([
                'print_banner'        => $this->upload($this->png(3000, 600), 'banner.png', 'image/png'),
                'print_banner_height' => 25,
                'print_banner_align'  => 'left',
                'print_banner_fit'    => 'natural',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings.edit', 'printing'));

        $path = settings('print_banner');
        $this->assertStringStartsWith('branding/print_banner-', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);

        $image = imagecreatefromstring(Storage::disk('public')->get($path));
        $this->assertSame(2400, imagesx($image));   // capped at 2400px wide
        $this->assertSame(480, imagesy($image));    // same shape
        $this->assertSame(0, (imagecolorat($image, 10, 10) >> 24) & 0x7F);      // opaque half
        $this->assertSame(127, (imagecolorat($image, 2390, 470) >> 24) & 0x7F); // still transparent

        $this->assertSame(25, settings('print_banner_height'));
        $this->assertSame('left', settings('print_banner_align'));
        $this->assertSame('natural', settings('print_banner_fit'));

        $log = AuditLog::where('module', 'settings')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Printing', $log->description);
        $this->assertStringContainsString('print_banner', $log->description);
    }

    public function test_jpeg_banner_is_resized_and_its_metadata_is_dropped(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'printing'), $this->payload([
                'print_banner' => $this->upload($this->jpegWithMetadata(3000, 1000, 'SECRET-CAMERA-SERIAL'), 'banner.jpg', 'image/jpeg'),
            ]))
            ->assertSessionHasNoErrors();

        $path = settings('print_banner');
        $this->assertStringEndsWith('.jpg', $path);
        $contents = Storage::disk('public')->get($path);
        $this->assertStringNotContainsString('SECRET-CAMERA-SERIAL', $contents);
        [$w, $h] = getimagesizefromstring($contents);
        $this->assertSame([2400, 800], [$w, $h]);
    }

    public function test_small_images_are_not_enlarged_and_signatures_are_capped(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'printing'), $this->payload([
                'print_banner'     => $this->upload($this->png(800, 200), 'small.png', 'image/png'),
                'print_sig1_name'  => 'Maria Santos',
                'print_sig1_image' => $this->upload($this->png(2000, 1000), 'sign.png', 'image/png'),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame([800, 200], array_slice(getimagesizefromstring(Storage::disk('public')->get(settings('print_banner'))), 0, 2));

        $signature = settings('print_sig1_image');
        $this->assertStringStartsWith('branding/print_sig1_image-', $signature);
        $this->assertSame([1200, 600], array_slice(getimagesizefromstring(Storage::disk('public')->get($signature)), 0, 2));
    }

    public function test_opaque_images_drop_the_alpha_channel_and_webp_is_converted(): void
    {
        Storage::fake('public');

        // An opaque PNG saved with an alpha channel comes back as plain RGB (IHDR colour type 2).
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'printing'), $this->payload([
                'print_banner' => $this->upload($this->png(900, 300, transparent: false), 'opaque.png', 'image/png'),
            ]))->assertSessionHasNoErrors();
        $this->assertSame(2, ord(Storage::disk('public')->get(settings('print_banner'))[25]));

        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD was built without WebP support.');
        }

        // WebP with transparency becomes PNG; an opaque WebP photo becomes JPEG.
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'printing'), $this->payload([
                'print_sig1_image' => $this->upload($this->webp(800, 200, transparent: true), 'sign.webp', 'image/webp'),
                'print_sig2_image' => $this->upload($this->webp(800, 200, transparent: false), 'photo.webp', 'image/webp'),
            ]))->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.png', settings('print_sig1_image'));
        $this->assertSame(6, ord(Storage::disk('public')->get(settings('print_sig1_image'))[25]));
        $this->assertStringEndsWith('.jpg', settings('print_sig2_image'));
    }

    public function test_pdfs_embed_transparent_png_flattened_onto_white(): void
    {
        $uri = PrintBranding::dataUri($this->png(400, 100));
        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $bytes = base64_decode(substr($uri, strlen('data:image/png;base64,')));
        $this->assertSame(2, ord($bytes[25])); // no alpha channel left for dompdf to process

        $image = imagecreatefromstring($bytes);
        $this->assertSame([400, 100], [imagesx($image), imagesy($image)]);
        $this->assertSame(0xFFFFFF, imagecolorat($image, 390, 90) & 0xFFFFFF); // transparent part is now white
        $this->assertSame(0x1E50C8, imagecolorat($image, 10, 10) & 0xFFFFFF);  // opaque part unchanged
    }

    public function test_banner_is_replaced_and_removed(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'printing'), $this->payload([
                'print_banner' => $this->upload($this->png(1200, 300), 'one.png', 'image/png'),
            ]))->assertSessionHasNoErrors();
        $first = settings('print_banner');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'printing'), $this->payload([
                'print_banner' => $this->upload($this->png(1200, 300), 'two.png', 'image/png'),
            ]))->assertSessionHasNoErrors();
        $second = settings('print_banner');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'printing'), $this->payload(['remove_print_banner' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertSame('', settings('print_banner'));
        Storage::disk('public')->assertMissing($second);
        $this->assertFalse(PrintBranding::hasBanner());
    }

    public function test_invalid_uploads_and_values_are_rejected(): void
    {
        Storage::fake('public');

        $cases = [
            'print_banner'        => UploadedFile::fake()->create('banner.pdf', 20, 'application/pdf'),
            'print_sig2_image'    => UploadedFile::fake()->create('sign.gif', 10, 'image/gif'),
            'print_banner_height' => 70,
            'print_sig1_height'   => 4,
            'print_banner_align'  => 'top',
            'print_banner_fit'    => 'stretch',
            'print_footer_text'   => 'Records of {bogus}.',
            'print_sig1_name'     => str_repeat('a', 101),
        ];

        foreach ($cases as $key => $value) {
            $this->actingAs($this->admin)
                ->put(route('admin.settings.update', 'printing'), $this->payload([$key => $value]))
                ->assertSessionHasErrors($key);
        }

        // Over the 5 MB limit.
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'printing'), $this->payload([
                'print_banner' => UploadedFile::fake()->image('huge.png', 100, 100)->size(6000),
            ]))
            ->assertSessionHasErrors('print_banner');

        // A text file renamed to .png is refused (content is sniffed).
        $fake = $this->tempPath('png');
        file_put_contents($fake, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'printing'), $this->payload([
                'print_banner' => new UploadedFile($fake, 'banner.png', 'image/png', null, true),
            ]))
            ->assertSessionHasErrors('print_banner');

        $this->assertSame('', settings('print_banner'));
        $this->assertSame(30, settings('print_banner_height'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_viewer_cannot_change_printing_settings(): void
    {
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)->get(route('admin.settings.edit', 'printing'))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.settings.update', 'printing'), $this->payload(['print_sig1_name' => 'X']))->assertForbidden();
        $this->assertSame('', settings('print_sig1_name'));
    }

    // ─── Documents ───────────────────────────────────────────────────────────

    public function test_defaults_print_documents_as_before(): void
    {
        settings()->setMany(['clinic_name' => 'Riverside Clinic']);
        $patient = Patient::factory()->create();
        PatientLog::factory()->create(['patient_id' => $patient->id, 'log_date' => '2026-09-10']);

        $this->actingAs($this->admin);

        $report = $this->reportHtml();
        $this->assertStringContainsString('Riverside Clinic', $report);          // logo + clinic header (_brand)
        $this->assertStringContainsString('Page <span class="pagenum"></span>', $report);
        $this->assertStringNotContainsString('letterhead-banner', $report);
        $this->assertStringNotContainsString('class="signatures"', $report);
        $this->assertStringNotContainsString('class="footer-note"', $report);

        // Health record: formal letterhead (logo and names), footer line, a line to sign by hand.
        $health = $this->healthHtml($patient);
        $this->assertStringContainsString('class="letterhead"', $health);
        $this->assertStringContainsString('Riverside Clinic', $health);
        $this->assertStringContainsString('Health Record', $health);
        $this->assertStringContainsString('Prepared by '.e($this->admin->name), $health);
        $this->assertStringContainsString('Confidential health information from the records of Riverside Clinic.', $health);
        $this->assertStringContainsString('This is a true copy of the health record kept by Riverside Clinic.', $health);
        $this->assertStringContainsString('Signature over printed name', $health);
        $this->assertStringNotContainsString('letterhead-banner', $health);
        $this->assertStringNotContainsString('class="signatures"', $health);

        // Browser print pages: the report page prints the school letterhead (no banner, no signatures).
        $this->get(route('reports.daily', ['date' => '2026-09-10']))->assertOk()
            ->assertSee('print-identity', false)->assertDontSee('letterhead-banner', false)
            ->assertDontSee('print-signoff', false);
        $this->get(route('patients.health-report', $patient))->assertOk()
            ->assertDontSee('print-letterhead', false)
            ->assertSee('Confidential health information from the records of Riverside Clinic.');
    }

    public function test_documents_print_the_banner_and_configured_signatories(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/print_banner-test.png', file_get_contents($this->png(2400, 400)));
        Storage::disk('public')->put('branding/print_sig1_image-test.png', file_get_contents($this->png(600, 200)));

        settings()->setMany([
            'clinic_name'                  => 'Riverside Clinic',
            'print_banner'                 => 'branding/print_banner-test.png',
            'print_banner_height'          => 40,
            'print_banner_fit'             => 'width',
            'print_banner_align'           => 'right',
            'print_prepared_by_on_reports' => true,
            'print_sig1_name'              => 'Maria Santos',
            'print_sig1_position'          => 'School Nurse',
            'print_sig1_license'           => '0123456',
            'print_sig1_image'             => 'branding/print_sig1_image-test.png',
            'print_sig1_height'            => 12,
            'print_sig2_label'             => 'Approved by',
            'print_sig2_name'              => 'Dr. Jose Reyes',
            'print_sig2_on_health'         => false,
            'print_sig3_name'              => '',   // no name: never printed
        ]);

        $patient = Patient::factory()->create();
        $this->actingAs($this->admin);

        $report = $this->reportHtml();
        // Banner replaces the logo + clinic header, at 182 x 30.33 mm (full width, shape kept).
        $this->assertStringContainsString('letterhead-banner', $report);
        $this->assertStringContainsString('text-align: right', $report);
        $this->assertStringContainsString('width: 182mm; height: 30.33mm;', $report);
        $this->assertStringContainsString('data:image/png;base64,', $report);
        $this->assertSame(1, substr_count($report, 'Riverside Clinic')); // only the PDF author metadata
        $this->assertStringContainsString('Daily report', $report);
        // Signatures: the person printing, then signatories 1 and 2.
        $this->assertStringContainsString('Prepared by:', $report);
        $this->assertStringContainsString(e($this->admin->name), $report);
        $this->assertStringContainsString('Maria Santos', $report);
        $this->assertStringContainsString('School Nurse', $report);
        $this->assertStringContainsString('License No. 0123456', $report);
        $this->assertStringContainsString('Approved by:', $report);
        $this->assertStringContainsString('Dr. Jose Reyes', $report);
        $this->assertStringContainsString('width:36mm; height:12mm;', $report); // signature image at its set height
        $signatures = (string) strstr($report, 'class="signatures"');
        $this->assertLessThan(strpos($signatures, 'Maria Santos'), strpos($signatures, e($this->admin->name))); // person printing comes first

        $health = $this->healthHtml($patient);
        $this->assertStringContainsString('letterhead-banner', $health);
        $this->assertStringContainsString('width: 174mm', $health);
        $this->assertStringContainsString('Health Record', $health);
        $this->assertStringNotContainsString('class="letterhead"', $health);      // the banner replaces the logo and names
        $this->assertStringContainsString('Maria Santos', $health);
        $this->assertStringNotContainsString('Dr. Jose Reyes', $health);          // turned off for health records
        $this->assertStringNotContainsString('Signature over printed name', $health); // signatories instead of a blank line
        $this->assertStringContainsString('Prepared by '.e($this->admin->name), $health); // still in the footer

        // The real PDFs render with the banner and signatures.
        $pdf = $this->get(route('reports.export', ['type' => 'daily', 'date' => '2026-09-10', 'format' => 'pdf']))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->get(route('patients.health-report.pdf', $patient))->assertOk();

        // Browser print pages show the same banner and signatures.
        $this->get(route('reports.daily', ['date' => '2026-09-10']))->assertOk()
            ->assertSee('print-letterhead', false)
            ->assertSee('storage/branding/print_banner-test.png', false)
            ->assertSee('print-signoff', false)
            ->assertSee('Maria Santos')
            ->assertSee('Dr. Jose Reyes');
        $this->get(route('patients.health-report', $patient))->assertOk()
            ->assertSee('storage/branding/print_banner-test.png', false)
            ->assertSee('Maria Santos')
            ->assertDontSee('Dr. Jose Reyes');
    }

    public function test_footer_text_and_page_numbers_follow_the_settings(): void
    {
        settings()->setMany([
            'org_name'                   => 'Mabini High School',
            'print_footer_text'          => 'Property of {school}. Do not copy.',
            'print_footer_on_reports'    => true,
            'print_footer_on_health'     => false,
            'print_page_numbers_reports' => false,
            'print_page_numbers_health'  => true,
            'print_prepared_by_on_health' => true,
        ]);

        $patient = Patient::factory()->create();
        $this->actingAs($this->admin);

        $report = $this->reportHtml();
        $this->assertStringContainsString('Property of Mabini High School. Do not copy.', $report);
        $this->assertStringNotContainsString('class="pagenum"', $report);

        $health = $this->healthHtml($patient);
        $this->assertStringNotContainsString('Property of', $health);
        // "Prepared by" moved from the footer into the signatures.
        $this->assertStringContainsString('Prepared by:', $health);
        $this->assertStringNotContainsString('Prepared by '.e($this->admin->name), $health);
        // Page numbers are drawn on the health record PDF ("Page 1 of 1").
        $pdf = $this->get(route('patients.health-report.pdf', $patient))->assertOk()->getContent();
        $this->assertStringContainsString('Page 1 of 1', $this->pdfText($pdf));
        $this->assertStringNotContainsString('Property of', $this->pdfText($pdf));

        $this->get(route('reports.daily'))->assertOk()->assertSee('Property of Mabini High School. Do not copy.');
    }

    public function test_documents_name_the_school_and_clinic_never_the_product(): void
    {
        settings()->setMany([
            'app_name'          => 'SchoolCare',
            'org_name'          => 'Mabini High School',
            'clinic_name'       => 'Mabini Health Office',
            'print_footer_text' => 'Records of {clinic}, {school} ({app}).',
            'print_footer_on_reports' => true,
        ]);
        $patient = Patient::factory()->create();
        $this->actingAs($this->admin);

        $report = $this->reportHtml();
        $health = $this->healthHtml($patient);
        foreach ([$report, $health] as $html) {
            $this->assertStringContainsString('Mabini High School', $html);
            $this->assertStringContainsString('Mabini Health Office', $html);
            $this->assertStringContainsString('<meta name="author" content="Mabini High School">', $html);
            $this->assertStringNotContainsString('SchoolCare', $html);
        }
        $this->assertStringContainsString('Records of Mabini Health Office, Mabini High School (Mabini High School).', $report);
        $this->assertStringContainsString('Confidential', $health);

        // Browser print: the report page prints the school letterhead, the health page names the school.
        $page = $this->get(route('reports.daily'))->assertOk()->getContent();
        $identity = (string) strstr((string) strstr($page, 'print-identity'), '</table>', true);
        $this->assertStringContainsString('Mabini High School', $identity);
        $this->assertStringContainsString('Mabini Health Office', $identity);
        $this->assertStringNotContainsString('SchoolCare', $identity);
        $this->assertStringNotContainsString('brand/logo.png', $identity); // no product mark

        $healthPage = $this->get(route('patients.health-report', $patient))->assertOk()->getContent();
        $letterhead = substr($healthPage, max(0, strpos($healthPage, 'id="hr-title"') - 700), 700);
        $this->assertStringContainsString('Mabini Health Office, Mabini High School', $letterhead);
        $this->assertStringNotContainsString('SchoolCare', $letterhead);
        $this->assertStringNotContainsString('brand/logo.png', $letterhead);

        // Neither name set: a neutral "School Clinic", still never the product name.
        settings()->setMany(['org_name' => '', 'clinic_name' => '']);
        $report = $this->reportHtml();
        $this->assertStringContainsString('School Clinic', $report);
        $this->assertStringNotContainsString('SchoolCare', $report);
        $health = $this->healthHtml($patient);
        $this->assertStringContainsString('School Clinic', $health);
        $this->assertStringContainsString('kept by School Clinic.', $health);
        $this->assertStringNotContainsString('SchoolCare', $health);
        $this->assertStringNotContainsString('SchoolCare', $this->pdfText($this->get(route('patients.health-report.pdf', $patient))->getContent()));
    }

    public function test_banner_box_keeps_the_shape_within_the_page(): void
    {
        // Full page width: fills 182 mm unless taller than the height setting.
        $this->assertSame(['width_mm' => 182.0, 'height_mm' => 30.33], PrintBranding::bannerBox(2400, 400, 40, 'width', 182));
        $this->assertSame(['width_mm' => 120.0, 'height_mm' => 30.0], PrintBranding::bannerBox(1200, 300, 30, 'width', 182));
        // Natural size: exactly the set height, never wider than the page.
        $this->assertSame(['width_mm' => 80.0, 'height_mm' => 20.0], PrintBranding::bannerBox(1200, 300, 20, 'natural', 182));
        $this->assertSame(['width_mm' => 182.0, 'height_mm' => 18.2], PrintBranding::bannerBox(3000, 300, 60, 'natural', 182));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** A valid form for the printing group (the defaults), with overrides. */
    private function payload(array $overrides = []): array
    {
        $base = [
            'print_banner_height'          => 30,
            'print_banner_align'           => 'center',
            'print_banner_fit'             => 'width',
            'print_prepared_by_label'      => 'Prepared by',
            'print_prepared_by_on_reports' => '0',
            'print_prepared_by_on_health'  => '0',
            'print_footer_text'            => 'Confidential health information from the records of {clinic}.',
            'print_footer_on_reports'      => '0',
            'print_footer_on_health'       => '1',
            'print_page_numbers_reports'   => '1',
            'print_page_numbers_health'    => '0',
        ];

        foreach ([1 => 'Prepared by', 2 => 'Noted by', 3 => 'Approved by'] as $n => $caption) {
            $base += [
                "print_sig{$n}_label"      => $caption,
                "print_sig{$n}_name"       => '',
                "print_sig{$n}_position"   => '',
                "print_sig{$n}_license"    => '',
                "print_sig{$n}_height"     => 15,
                "print_sig{$n}_on_reports" => '1',
                "print_sig{$n}_on_health"  => '1',
            ];
        }

        return array_merge($base, $overrides);
    }

    private function reportHtml(): string
    {
        return view('reports.pdf.daily', app(ReportService::class)->dailyReport('2026-09-10'))->render();
    }

    private function healthHtml(Patient $patient): string
    {
        return view('patients.pdf.health-report', app(HealthReportService::class)->build($patient))->render();
    }

    /** A temporary file name with a real extension (removed in tearDown). */
    private function tempPath(string $extension): string
    {
        return $this->tempFiles[] = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('print-test-', true).'.'.$extension;
    }

    private function upload(string $path, string $name, string $mime): UploadedFile
    {
        return new UploadedFile($path, $name, $mime, null, true);
    }

    /**
     * PNG (always saved with an alpha channel) whose left half is opaque blue and
     * right half fully transparent, or light grey when $transparent is false.
     */
    private function png(int $width, int $height, bool $transparent = true): string
    {
        $path = $this->tempPath('png');
        imagepng($this->canvas($width, $height, $transparent), $path);

        return $path;
    }

    private function webp(int $width, int $height, bool $transparent): string
    {
        $path = $this->tempPath('webp');
        imagewebp($this->canvas($width, $height, $transparent), $path, 90);

        return $path;
    }

    private function canvas(int $width, int $height, bool $transparent): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, $transparent
            ? imagecolorallocatealpha($image, 0, 0, 0, 127)
            : imagecolorallocatealpha($image, 230, 230, 230, 0));
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height - 1, imagecolorallocatealpha($image, 30, 80, 200, 0));

        return $image;
    }

    /** JPEG with an extra APP1 (EXIF-style) block carrying $secret. */
    private function jpegWithMetadata(int $width, int $height, string $secret): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 240, 240, 240));
        ob_start();
        imagejpeg($image, null, 80);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $payload = "Exif\0\0".$secret;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        $path = $this->tempPath('jpg');
        file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

        return $path;
    }
}
