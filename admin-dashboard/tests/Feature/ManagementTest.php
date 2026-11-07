<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [realpath(base_path('../web/database/migrations'))], '--realpath' => true];
    }

    private function owner(): User
    {
        $owner = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($owner);

        return $owner;
    }

    public function test_banner_workspace_shows_preview_schedule_and_dedicated_editor(): void
    {
        $this->owner();
        $id = DB::table('storefront_banners')->insertGetId(['name' => 'Autumn collection', 'image_url' => 'https://example.com/banner.jpg', 'alt_text' => 'New phones', 'status' => 'active', 'sort_order' => 2, 'start_date' => now()->addDay()]);
        $this->get('/manage/banners')->assertOk()->assertSee('Homepage banners')->assertSee('Scheduled')->assertSee('https://example.com/banner.jpg', false);
        $this->get('/manage/banners/'.$id.'/edit')->assertOk()->assertSee('data-banner-editor', false)->assertSee('Banner preview')->assertSee('Autumn collection');
        $this->get('/manage/banners/create')->assertOk()->assertSee('value="inactive" selected', false);
        $this->get('/manage/banners?search=Missing')->assertOk()->assertDontSee('Autumn collection');
        $staff = User::factory()->create(['role' => 'admin', 'status' => 'active', 'admin_permissions' => ['catalog']]);
        $this->actingAs($staff)->get('/manage/banners')->assertForbidden();
    }

    public function test_account_and_appearance_are_separate_and_sidebar_uses_one_accordion_group(): void
    {
        $this->owner();
        $this->get('/settings/account')->assertOk()->assertSee('Administrator account')->assertDontSee('id="settings-theme"', false);
        $this->get('/settings/appearance')->assertOk()->assertSee('Display preferences')->assertSee('id="settings-theme"', false)->assertDontSee('<dt>Email</dt>', false);
        $this->get('/dashboard')->assertSee('name="workspace-navigation"', false)->assertDontSee('Account &amp; appearance', false);
    }

    public function test_management_pages_and_report_types_render(): void
    {
        $this->owner();
        foreach (['banners', 'categories', 'brands', 'attributes', 'attribute-values', 'coupons', 'shipping-methods', 'shipping-zones', 'reviews'] as $resource) {
            $this->get('/manage/'.$resource)->assertOk();
            if ($resource !== 'reviews') {
                $this->get('/manage/'.$resource.'/create')->assertOk();
            }
        }
        foreach (['/reservations', '/payments', '/refunds', '/staff', '/staff/create', '/audit', '/store-settings'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['inventory', 'products', 'customers', 'payments', 'refunds'] as $type) {
            $this->get('/reports?type='.$type)->assertOk();
            $this->get('/reports/export?type='.$type)->assertOk()->assertDownload($type.'-report.csv');
        }
        $this->get('/dashboard')->assertSee('Categories')->assertSee('Staff &amp; permissions', false)->assertDontSee('Download orders CSV');
    }

    public function test_catalog_mutations_are_forwarded_to_backend(): void
    {
        $owner = $this->owner();
        Http::fake(['*' => Http::response(['data' => ['id' => 1]], 201)]);
        $this->post('/manage/brands', ['name' => 'Acme', 'status' => 'active'])->assertRedirect('/manage/brands');
        $this->put('/manage/brands/1', ['name' => 'Updated', 'status' => 'inactive'])->assertRedirect('/manage/brands');
        $this->delete('/manage/brands/1')->assertRedirect();
        $this->patch('/manage/brands/1/restore')->assertRedirect();
        Http::assertSentCount(4);
        $this->assertSame(0, $owner->tokens()->count());
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/admin/manage/brands') && $request['name'] === 'Acme');
    }

    public function test_limited_staff_land_on_allowed_page_and_cannot_open_other_modules(): void
    {
        $staff = User::factory()->create(['role' => 'admin', 'status' => 'active', 'admin_permissions' => ['catalog']]);
        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/products');
        $this->get('/products')->assertOk()->assertSee('Categories')->assertDontSee('Staff &amp; permissions', false);
        $this->get('/staff')->assertForbidden();
        $this->get('/payments')->assertForbidden();
        $this->get('/manage/coupons')->assertForbidden();
        Http::fake();
        $this->post('/refunds', [])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_product_variant_image_customer_and_order_workspaces_render(): void
    {
        $this->owner();
        $category = DB::table('categories')->insertGetId(['name' => 'Electronics', 'slug' => 'electronics', 'status' => 'active']);
        $product = DB::table('products')->insertGetId(['category_id' => $category, 'name' => 'Phone', 'slug' => 'phone', 'price' => 99, 'status' => 'active']);
        $variant = DB::table('product_variants')->insertGetId(['product_id' => $product, 'name' => 'Default', 'sku' => 'PHONE', 'price' => 99, 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        foreach (['/products/'.$product.'/variants', '/products/'.$product.'/variants/create', '/products/'.$product.'/variants/'.$variant.'/edit', '/products/'.$product.'/images', '/customers/'.$customer->id] as $url) {
            $this->get($url)->assertOk();
        }
        Http::fake(['*' => Http::response(['message' => 'Saved'], 200)]);
        $this->post('/products/'.$product.'/variants', ['sku' => 'NEW', 'name' => 'New', 'price' => 50, 'status' => 'active', 'low_stock_threshold' => 5, 'initial_stock' => 4, 'values' => []])->assertRedirect('/products/'.$product.'/variants');
        $this->patch('/customers/'.$customer->id, ['status' => 'blocked'])->assertRedirect();
        $this->post('/refunds', ['order_id' => 1, 'amount' => 10, 'reason' => 'Test'])->assertRedirect();
        Http::assertSentCount(3);
    }
}
