<?php

namespace Tests\Feature\Clinical;

use App\Models\Medicine;
use App\Models\Patient;

/**
 * Searchable dropdowns (resources/js/ui/select-search.js) upgrade long
 * select.form-select lists in the browser. The enhancer needs no markup of its
 * own, so these tests guard what it relies on: the bundle loads it, and the long
 * clinical dropdowns stay labelled .form-select elements with more than six
 * choices and no data-no-search opt-out.
 */
class SearchableDropdownsTest extends ClinicalTestCase
{
    public function test_the_app_bundle_loads_the_enhancer_and_its_styles(): void
    {
        $this->assertFileExists(resource_path('js/ui/select-search.js'));
        $this->assertFileExists(resource_path('scss/components/_select-search.scss'));
        $this->assertStringContainsString("import './ui/select-search';", file_get_contents(resource_path('js/app.js')));
        $this->assertStringContainsString('@import "components/select-search";', file_get_contents(resource_path('scss/app.scss')));
    }

    public function test_long_clinical_dropdowns_render_as_labelled_form_selects(): void
    {
        Medicine::factory()->count(8)->create();
        Patient::factory()->count(8)->create();

        $pages = [
            route('dispensing.create') => ['patientSelect', 'medicineSelect'],
            route('inventory.stock-in.form') => ['medicineSelect'],
            route('consultations.create') => ['patientSelect'],
            route('appointments.create') => ['patientSelect'],
        ];

        foreach ($pages as $url => $ids) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            foreach ($ids as $id) {
                $this->assertMatchesRegularExpression('/<select\b[^>]*\bid="'.$id.'"[^>]*>(.*?)<\/select>/s', $html, "{$url}: #{$id} is missing");
                preg_match('/<select\b([^>]*\bid="'.$id.'"[^>]*)>(.*?)<\/select>/s', $html, $m);
                [, $attributes, $options] = $m;

                $this->assertMatchesRegularExpression('/class="[^"]*\bform-select\b/', $attributes, "{$url}: #{$id} is not a .form-select");
                $this->assertStringNotContainsString('data-no-search', $attributes, "{$url}: #{$id} opts out of search");
                $this->assertStringContainsString('for="'.$id.'"', $html, "{$url}: #{$id} has no <label for>");
                $this->assertGreaterThan(6, preg_match_all('/<option\b[^>]*\bvalue="[^"]+"/', $options), "{$url}: #{$id} lists 6 choices or fewer");
                $this->assertMatchesRegularExpression('/<option\b[^>]*\bvalue=""/', $options, "{$url}: #{$id} has no empty prompt option");
            }
        }
    }

    public function test_the_dispensing_medicine_options_keep_the_data_the_page_script_reads(): void
    {
        Medicine::factory()->count(7)->create();
        $medicine = Medicine::factory()->create(['name' => 'Searchable Paracetamol 500mg', 'quantity' => 40, 'low_stock_threshold' => 5]);

        $this->actingAs($this->admin)
            ->get(route('dispensing.create'))
            ->assertOk()
            ->assertSee('<option value="'.$medicine->id.'"', false)
            ->assertSee('data-qty="40"', false)
            ->assertSee('data-low="5"', false)
            ->assertSee('Searchable Paracetamol 500mg');
    }
}
