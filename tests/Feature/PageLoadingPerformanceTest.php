<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PageLoadingPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_ui_assets_are_local_versioned_and_present(): void
    {
        $this->withoutVite();
        $this->get('/login')->assertOk()->assertDontSee('cdn.jsdelivr.net')->assertDontSee('code.jquery.com')
            ->assertSee('vendor/bootstrap-5.3.0/dist/css/bootstrap.min.css');
        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        $this->get('/export/receiving')->assertOk()->assertDontSee('cdn.jsdelivr.net')
            ->assertSee('css/layout-shell.css?v=')->assertSee('js/layout-shell.js?v=')->assertSee('js/toast.js?v=')
            ->assertDontSee('function cleanupOrphanBackdrops');
        $manifest = json_decode(file_get_contents(public_path('vendor/ui-manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['packages'] as $package) foreach ($package['files'] as $file) {
            $path = public_path('vendor/'.$package['name'].'-'.$package['version'].'/'.$file['path']);
            $this->assertFileExists($path);
            $this->assertSame($file['sha256'], hash_file('sha256', $path));
        }
    }

    public function test_scan_and_stock_overview_do_not_read_unused_placement_catalogs(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        foreach (['/export/receiving', '/export/stock'] as $path) {
            DB::enableQueryLog(); DB::flushQueryLog();
            $this->get($path)->assertOk();
            $queries = DB::getQueryLog(); DB::disableQueryLog();
            foreach ($queries as $query) {
                if ($path === '/export/receiving') {
                    $this->assertStringNotContainsString('master_locations', $query['query']);
                    $this->assertStringNotContainsString('export_stock_pallets', $query['query']);
                }
            }
            if ($path === '/export/stock') {
                // One query for occupied export locations, without the old pallet dropdown/catalog.
                $catalog = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'master_locations'))->values();
                $this->assertCount(1, $catalog);
                $this->assertStringContainsString('export_stock_boxes', $catalog[0]['query']);
            }
        }
    }
}
