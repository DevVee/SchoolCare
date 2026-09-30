<?php

namespace Tests\Feature\Parity;

use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class PatientImportTest extends ParityTestCase
{
    private function csv(array $rows): UploadedFile
    {
        $lines = array_map(fn ($r) => implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $r)), $rows);

        return UploadedFile::fake()->createWithContent('patients.csv', implode("\n", $lines)."\n");
    }

    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new XlsxWriter();
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'patients.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_csv_preview_flags_errors_and_duplicates_then_imports_only_valid_rows(): void
    {
        Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'category' => 'college', 'birthdate' => null, 'student_id' => 'S-100']);

        $file = $this->csv([
            ['last_name', 'first_name', 'gender', 'category', 'grade_year', 'student_id', 'birthdate', 'contact_number'],
            ['Reyes', 'Juan', 'Male', 'College', '1st Year', 'S-200', '2005-03-04', '9171234567'],   // valid (SSCMS spellings)
            ['Santos', 'Maria', 'F', 'JHS', 'Grade 7', '', '', ''],                                   // valid, no birthdate
            ['Lopez', '', 'Male', 'College', '', '', '', ''],                                          // error: first name
            ['Tan', 'Leo', 'Robot', 'Martians', '', '', '', ''],                                       // error: sex + category
            ['Cruz', 'Ana', 'Female', 'College', '', '', '', ''],                                      // duplicate of existing (name + category)
            ['Uy', 'Ben', 'Male', 'College', '', 'S-100', '', ''],                                     // duplicate student ID
            ['Reyes', 'Juan', 'Male', 'College', '', 'S-201', '2005-03-04', ''],                      // duplicate of row 2 (name + birthdate)
            ['Go', 'Kim', 'Male', 'College', 'Grade 7', '', '', ''],                                   // error: level not offered for college
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('patients.import.preview'), ['file' => $file])
            ->assertOk()
            ->assertSee('Check the import')
            ->assertSee('First name is required.')
            ->assertSee('Already registered as')
            ->assertSee('Same name and birthdate as line 2');

        $analysis = $response->viewData('analysis');
        $this->assertSame(['valid' => 2, 'error' => 3, 'duplicate' => 3, 'total' => 8], $analysis['counts']);
        $this->assertSame(1, Patient::count(), 'Preview must not save anything.');

        $token = $response->viewData('token');

        $this->actingAs($this->admin)
            ->post(route('patients.import.store'), ['token' => $token])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(3, Patient::count());
        $juan = Patient::where('first_name', 'Juan')->firstOrFail();
        $this->assertSame('male', $juan->sex);
        $this->assertSame('college', $juan->category);
        $this->assertSame('1st Year', $juan->year_level);
        $this->assertSame('09171234567', $juan->contact_number, 'Leading zero restored');
        $this->assertNotEmpty($juan->patient_number);

        $maria = Patient::where('first_name', 'Maria')->firstOrFail();
        $this->assertSame('junior_high', $maria->category);
        $this->assertNull($maria->birthdate);
        $this->assertNull($maria->age);

        $this->assertTrue(AuditLog::where('module', 'patients')->where('action', 'imported')->exists());

        // The stored upload is gone; confirming again does nothing.
        $this->actingAs($this->admin)
            ->post(route('patients.import.store'), ['token' => $token])
            ->assertRedirect(route('patients.import.create'));
        $this->assertSame(3, Patient::count());
    }

    public function test_xlsx_import_reads_dates_and_labels(): void
    {
        $file = $this->xlsx([
            ['Last Name', 'First Name', 'Sex', 'Category', 'Year Level / Grade', 'Section', 'Birthdate (YYYY-MM-DD)'],
            ['Dela Cruz', 'Pedro', 'male', 'Senior High School', 'Grade 11', 'Section A', new \DateTimeImmutable('2008-01-15')],
            ['Bautista', 'Rosa', 'female', 'senior_high', 'Grade 13', '', ''],
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('patients.import.preview'), ['file' => $file])
            ->assertOk();

        $analysis = $response->viewData('analysis');
        $this->assertSame(1, $analysis['counts']['valid']);
        $this->assertSame(1, $analysis['counts']['error']);
        $this->assertArrayHasKey('year_level', $analysis['rows'][1]['errors']);

        $this->actingAs($this->admin)
            ->post(route('patients.import.store'), ['token' => $response->viewData('token')])
            ->assertSessionHas('success');

        $pedro = Patient::where('first_name', 'Pedro')->firstOrFail();
        $this->assertSame('2008-01-15', $pedro->birthdate->format('Y-m-d'));
        $this->assertSame('senior_high', $pedro->category);
        $this->assertSame('Section A', $pedro->section);
        $this->assertFalse(Patient::where('first_name', 'Rosa')->exists());
    }

    public function test_missing_required_columns_are_reported(): void
    {
        $file = $this->csv([['first_name', 'last_name'], ['Ana', 'Cruz']]);

        $this->actingAs($this->admin)
            ->post(route('patients.import.preview'), ['file' => $file])
            ->assertOk()
            ->assertSee('missing required columns');
    }

    public function test_import_requires_permission_and_rejects_other_files(): void
    {
        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer)->get(route('patients.import.create'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('patients.import.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('patients.import.template', ['format' => 'xlsx']))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('patients.import.preview'), ['file' => UploadedFile::fake()->create('evil.php', 1)])
            ->assertSessionHasErrors('file');

        // A "csv" that is really binary is refused by the content sniff.
        $this->actingAs($this->admin)
            ->post(route('patients.import.preview'), ['file' => UploadedFile::fake()->createWithContent('x.csv', "\0\0\0binary")])
            ->assertSessionHasErrors('file');
    }
}
