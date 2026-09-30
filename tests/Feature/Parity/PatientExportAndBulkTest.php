<?php

namespace Tests\Feature\Parity;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Support\SpreadsheetCell;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class PatientExportAndBulkTest extends ParityTestCase
{
    private function downloadedFile($response): string
    {
        $file = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($file);

        return $file;
    }

    public function test_spreadsheet_cell_neutralises_formulas(): void
    {
        $this->assertSame("'=1+1", SpreadsheetCell::safe('=1+1'));
        $this->assertSame("'+SUM(A1)", SpreadsheetCell::safe('+SUM(A1)'));
        $this->assertSame("'-2+3", SpreadsheetCell::safe('-2+3'));
        $this->assertSame("'@cmd", SpreadsheetCell::safe('@cmd'));
        $this->assertSame('Ana', SpreadsheetCell::safe('Ana'));
        $this->assertSame(12, SpreadsheetCell::safe(12));
    }

    public function test_csv_export_applies_filters_and_is_formula_safe(): void
    {
        Patient::factory()->create(['first_name' => '=HYPERLINK("http://x","click")', 'last_name' => 'Evil', 'category' => 'college']);
        Patient::factory()->create(['first_name' => 'Other', 'last_name' => 'Person', 'category' => 'teacher']);

        $response = $this->actingAs($this->admin)
            ->get(route('patients.export', ['format' => 'csv', 'category' => 'college']))
            ->assertOk();

        $csv = file_get_contents($this->downloadedFile($response));
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
        $this->assertStringNotContainsString('Person', $csv, 'The category filter applies to the export.');

        $this->assertTrue(AuditLog::where('module', 'patients')->where('action', 'exported')->exists());
    }

    public function test_xlsx_export_writes_text_not_formulas(): void
    {
        Patient::factory()->create(['first_name' => '=1+2', 'last_name' => 'Formula']);

        $response = $this->actingAs($this->admin)
            ->get(route('patients.export', ['format' => 'xlsx']))
            ->assertOk();

        $reader = new XlsxReader();
        $reader->open($this->downloadedFile($response));
        $values = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $values = array_merge($values, $row->toArray());
            }
        }
        $reader->close();

        $this->assertContains("'=1+2", $values);
        $this->assertNotContains(3, $values);
    }

    public function test_export_requires_permission(): void
    {
        $this->actingAs($this->userWithRole('staff'))
            ->get(route('patients.export', ['format' => 'csv']))
            ->assertForbidden();
    }

    public function test_bulk_move_activate_and_archive(): void
    {
        $a = Patient::factory()->create(['category' => 'junior_high', 'year_level' => 'Grade 7', 'section' => 'Section A']);
        $b = Patient::factory()->create(['category' => 'junior_high', 'year_level' => 'Grade 7', 'section' => 'Section B']);

        $this->actingAs($this->admin)
            ->post(route('patients.bulk'), ['action' => 'move', 'ids' => [$a->id, $b->id], 'section' => 'Section C'])
            ->assertSessionHas('success');
        $this->assertSame(['Section C', 'Section C'], [$a->fresh()->section, $b->fresh()->section]);

        // A year level that is not offered for the category is refused.
        $this->actingAs($this->admin)
            ->post(route('patients.bulk'), ['action' => 'move', 'ids' => [$a->id], 'category' => 'college', 'year_level' => 'Grade 7'])
            ->assertSessionHasErrors('year_level');

        $this->actingAs($this->admin)
            ->post(route('patients.bulk'), ['action' => 'deactivate', 'ids' => [$a->id]])
            ->assertSessionHas('success');
        $this->assertFalse($a->fresh()->is_active);

        $this->actingAs($this->admin)
            ->post(route('patients.bulk'), ['action' => 'archive', 'ids' => [$a->id, $b->id]])
            ->assertSessionHas('success');
        $this->assertSame(0, Patient::count());
        $this->assertSame(2, Patient::onlyTrashed()->count());
        $this->assertTrue(AuditLog::where('action', 'bulk_archived')->exists());

        // Archiving needs delete-patients.
        $c = Patient::factory()->create();
        $this->actingAs($this->userWithPermissions(['view-patients', 'update-patients']))
            ->post(route('patients.bulk'), ['action' => 'archive', 'ids' => [$c->id]])
            ->assertForbidden();
        $this->assertNull($c->fresh()->deleted_at);
    }

    public function test_bulk_export_of_selected_rows(): void
    {
        $a = Patient::factory()->create(['last_name' => 'Chosen']);
        Patient::factory()->create(['last_name' => 'Skipped']);

        $response = $this->actingAs($this->admin)
            ->post(route('patients.bulk'), ['action' => 'export', 'format' => 'csv', 'ids' => [$a->id]])
            ->assertOk();

        $csv = file_get_contents($this->downloadedFile($response));
        $this->assertStringContainsString('Chosen', $csv);
        $this->assertStringNotContainsString('Skipped', $csv);
    }

    public function test_year_end_promotion_moves_each_level_once(): void
    {
        $g7a = Patient::factory()->create(['category' => 'junior_high', 'year_level' => 'Grade 7', 'section' => 'Section A']);
        $g7b = Patient::factory()->create(['category' => 'junior_high', 'year_level' => 'Grade 7']);
        $g8  = Patient::factory()->create(['category' => 'junior_high', 'year_level' => 'Grade 8']);
        $g10 = Patient::factory()->create(['category' => 'junior_high', 'year_level' => 'Grade 10', 'program_strand' => 'X']);
        $inactive = Patient::factory()->inactive()->create(['category' => 'junior_high', 'year_level' => 'Grade 7']);

        $this->actingAs($this->admin)
            ->get(route('patients.promote.form', ['category' => 'junior_high']))
            ->assertOk()
            ->assertSee('Grade 10');

        $payload = [
            'category' => 'junior_high',
            'from'     => ['Grade 10', 'Grade 9', 'Grade 8', 'Grade 7'],
            'mapping'  => ['senior_high|Grade 11', 'junior_high|Grade 10', 'junior_high|Grade 9', 'junior_high|Grade 8'],
            'clear_sections' => '1',
        ];

        $preview = $this->actingAs($this->admin)->post(route('patients.promote.preview'), $payload)->assertOk();
        $this->assertSame(4, $preview->viewData('total'));

        // Confirmation is required.
        $this->actingAs($this->admin)->post(route('patients.promote'), $payload)->assertSessionHasErrors('confirm');
        $this->assertSame('Grade 7', $g7a->fresh()->year_level);

        $this->actingAs($this->admin)
            ->post(route('patients.promote'), $payload + ['confirm' => '1'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Grade 8', $g7a->fresh()->year_level, '7 -> 8 must not cascade to 9');
        $this->assertNull($g7a->fresh()->section, 'Sections cleared');
        $this->assertSame('Grade 8', $g7b->fresh()->year_level);
        $this->assertSame('Grade 9', $g8->fresh()->year_level);
        $this->assertSame('senior_high', $g10->fresh()->category);
        $this->assertSame('Grade 11', $g10->fresh()->year_level);
        $this->assertNull($g10->fresh()->program_strand);
        $this->assertSame('Grade 7', $inactive->fresh()->year_level, 'Inactive patients are not promoted');

        $log = AuditLog::where('module', 'patients')->where('action', 'promoted')->sole();
        $this->assertStringContainsString('4 patient(s)', $log->description);
    }

    public function test_promotion_requires_update_permission(): void
    {
        $this->actingAs($this->userWithRole('viewer'))
            ->get(route('patients.promote.form'))
            ->assertForbidden();
    }
}
