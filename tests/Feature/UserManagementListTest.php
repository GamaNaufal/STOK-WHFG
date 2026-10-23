<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_and_filter_all_accounts_including_inactive_ones(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'name' => 'Rina Gudang', 'email' => 'rina@example.test',
            'role' => 'warehouse_operator', 'is_active' => false,
        ]);
        User::factory()->create([
            'name' => 'Rina Sales', 'email' => 'sales@example.test',
            'role' => 'sales', 'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('users.index', [
            'q' => 'rina', 'role' => 'warehouse_operator', 'status' => 'inactive',
        ]))->assertOk()
            ->assertSee('Rina Gudang')
            ->assertDontSee('Rina Sales')
            ->assertSee('1 akun')
            ->assertSee('Aktifkan lewat Edit');

        $this->get(route('users.index', ['q' => 'rina@example.test']))
            ->assertOk()->assertSee('Rina Gudang')->assertDontSee('Rina Sales');
    }

    public function test_list_is_paginated_and_filter_parameters_survive_page_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(16)->create(['role' => 'sales', 'is_active' => true]);

        $firstPage = $this->actingAs($admin)->get(route('users.index', ['role' => 'sales', 'status' => 'active']));
        $firstPage->assertOk()
            ->assertSee('Menampilkan 1–15 dari 16 akun')
            ->assertSee('role=sales', false)
            ->assertSee('status=active', false);

        $this->get(route('users.index', ['role' => 'sales', 'status' => 'active', 'page' => 2]))
            ->assertOk()->assertSee('Menampilkan 16–16 dari 16 akun');
    }

    public function test_own_account_does_not_offer_deactivation_from_the_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Akun Anda')
            ->assertDontSee('data-deactivate-url="'.route('users.destroy', $admin).'"', false);
    }
}
