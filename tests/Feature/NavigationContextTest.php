<?php

namespace Tests\Feature;

use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\NavigationContextService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_subpages_select_one_destination_even_when_permissions_share_a_key(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $cases = [
            'export.receiving.index' => 'export.receiving.index',
            'export.receiving.transactions.show' => 'export.receiving.index',
            'export.stock.show' => 'export.stock.index',
            'export.receiving.picking.show' => 'export.picking.index',
            'export.picking.show' => 'export.picking.index',
            'export.delivery-plans.create' => 'export.delivery-plans.index',
            'export.pallet-plans.show' => 'export.pallet-plans.index',
            'export.fulfillment.ready-form' => 'export.fulfillment.index',
            'export.merge.preview' => 'export.merge.index',
            'export.merge.result' => 'export.merge.index',
            'merge-pallet.store' => 'merge-pallet.index',
            'part-settings.edit' => 'part-settings.index',
            'export.locations.create' => 'export.locations.index',
            'reports.withdrawal.export' => 'reports.withdrawal',
            'reports.records.detail' => 'reports.stock-input',
            'delivery.edit' => 'delivery.create',
            'delivery.pick.verify' => 'delivery.pick.verification',
            'delivery.pick.issue.approve' => 'delivery.pick.issues',
            'delivery.pick.redo-options' => 'delivery.index',
            'stock-view.box-update' => 'stock-view.index',
            'part-settings.create' => 'part-settings.index',
            'locations.edit' => 'locations.index',
            'users.edit' => 'users.index',
            'access.update' => 'access.index',
            'box-not-full.export.approve' => 'box-not-full.approvals',
        ];
        foreach ($cases as $page => $destination) {
            $context = app(NavigationContextService::class)->build($user, $page);
            $active = collect($context['items'])->where('active', true);
            $this->assertCount(1, $active, $page);
            $this->assertSame($destination, $active->first()['route'], $page);
        }
    }

    public function test_navigation_preserves_allowed_destinations_for_every_role(): void
    {
        foreach (['admin', ...array_keys(config('feature_access.roles'))] as $role) {
            $user = User::factory()->create(compact('role'));
            $expected = array_column(app(FeatureAccessService::class)->navigation($user), 'route');
            if (in_array('merge-pallet.index', $expected, true)) {
                $expected = array_values(array_diff($expected, ['export.merge.index']));
            }
            $context = app(NavigationContextService::class)->build($user, 'profile.edit');
            $actual = array_column($context['items'], 'route');
            sort($expected);
            sort($actual);
            $this->assertSame($expected, $actual, $role);
            $this->assertCount(0, collect($context['items'])->where('active', true));
            $this->assertSame('Profil Saya', $context['breadcrumbs'][1]['label']);
        }
    }

    public function test_merge_has_one_warehouse_link_and_switches_only_between_allowed_scopes(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin_warehouse']));

        foreach (['domestic' => 'merge-pallet.index', 'export' => 'export.merge.index'] as $scope => $route) {
            $response = $this->get(route($route))->assertOk();
            $dom = new DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new DOMXPath($dom);
            $menu = $xpath->query('//nav[@id="mobileSidebar"]//*[@id="featureMenuwarehouse"]//a[@aria-label="Merge Pallet"]');
            $this->assertCount(1, $menu);
            $this->assertCount(1, $xpath->query('//nav[@id="mobileSidebar"]//a[contains(@href,"/merge-pallet")]'));
            $this->assertSame(route($route), $menu->item(0)->getAttribute('href'));
            $this->assertSame('page', $menu->item(0)->getAttribute('aria-current'));

            $switch = '//nav[@aria-label="Lingkup merge pallet" and @data-scope="'.$scope.'"]';
            $this->assertCount(2, $xpath->query($switch.'/a'));
            $this->assertSame(route($route), $xpath->query($switch.'/a[@aria-current="page"]')->item(0)->getAttribute('href'));
        }

        RoleFeatureAccess::create(['role' => 'admin_warehouse', 'permissions' => ['export_merge_pallet']]);
        $this->get(route('export.merge.index'))->assertOk()
            ->assertDontSee('aria-label="Lingkup merge pallet"', false)
            ->assertDontSee('href="'.route('merge-pallet.index').'"', false);
        $this->get(route('merge-pallet.index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'warehouse_operator']));
        $this->get(route('merge-pallet.index'))->assertOk()
            ->assertDontSee('aria-label="Lingkup merge pallet"', false)
            ->assertDontSee('href="'.route('export.merge.index').'"', false);
        $this->get(route('export.merge.index'))->assertForbidden();
    }

    public function test_input_only_role_uses_receiving_context_without_gaining_picking_menu(): void
    {
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => ['export_stock_input']]);
        $context = app(NavigationContextService::class)->build(User::factory()->create(['role' => 'export_operator']), 'export.receiving.picking.show');
        $this->assertSame('export.receiving.index', collect($context['items'])->firstWhere('active', true)['route']);
        $this->assertNotContains('export.picking.index', array_column($context['items'], 'route'));
    }

    public function test_context_has_readable_breadcrumbs_short_labels_and_administration_last(): void
    {
        $context = app(NavigationContextService::class)->build(User::factory()->create(['role' => 'admin']), 'part-settings.create');
        $this->assertSame(['Master Data', 'Nomor Part', 'Tambah'], array_column($context['breadcrumbs'], 'label'));
        $this->assertSame(route('part-settings.index'), $context['breadcrumbs'][1]['url']);
        $this->assertSame('Admin Utama', $context['role_label']);
        $this->assertSame('Administrasi', $context['items'][array_key_last($context['items'])]['group']);
        $receiving = collect($context['items'])->firstWhere('route', 'export.receiving.index');
        $this->assertSame('Penerimaan Transfer Box', $receiving['short_label']);
        $this->assertSame('Penerimaan Transfer Box Ekspor', $receiving['label']);
    }

    public function test_rendered_sidebar_has_one_current_link_and_shared_controls_are_accessible(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        foreach (['export.receiving.index' => 'export.receiving.index', 'part-settings.create' => 'part-settings.index'] as $page => $parent) {
            $response = $this->get(route($page))->assertOk()
                ->assertSee('aria-label="Jejak navigasi"', false)
                ->assertSee('id="globalSearchClear"', false)
                ->assertSee('type="module" src="'.asset('js/layout-shell.js'), false);
            $dom = new DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new DOMXPath($dom);
            $active = $xpath->query('//nav[@id="mobileSidebar"]//a[@aria-current="page"]');
            $this->assertCount(1, $active);
            $this->assertSame(route($parent), $active->item(0)->getAttribute('href'));
            $this->assertGreaterThan(0, $xpath->query('//button[contains(@class,"menu-group-toggle") and @type="button"]')->length);
        }
    }

    public function test_empty_custom_permissions_do_not_leak_links_into_breadcrumbs_or_search(): void
    {
        $this->withoutVite();
        RoleFeatureAccess::create(['role' => 'sales', 'permissions' => []]);
        $this->actingAs(User::factory()->create(['role' => 'sales']))->get(route('profile.edit'))->assertOk()
            ->assertDontSee(route('delivery.create'), false)
            ->assertDontSee(route('access.index'), false)
            ->assertDontSee(route('export.delivery-plans.index'), false)
            ->assertSee('Profil Saya')->assertSee('Keluar');
    }
}
