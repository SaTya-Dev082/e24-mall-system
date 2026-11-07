<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontBannerTest extends TestCase
{
    use RefreshDatabase;

    private function banner(array $overrides = []): array
    {
        return array_merge(['name' => 'Summer collection', 'image_url' => 'https://example.com/banner.jpg', 'alt_text' => 'Summer collection banner', 'link_url' => 'https://example.com/products', 'headline' => 'Discover MacBook Air', 'description' => 'A fresh everyday essential.', 'button_text' => 'Explore collection', 'status' => 'active', 'sort_order' => 0], $overrides);
    }

    public function test_admin_can_publish_edit_archive_and_restore_home_banner(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']), 'sanctum');
        $id = $this->postJson('/api/v1/admin/manage/banners', $this->banner())->assertCreated()->json('data.id');
        $this->get('/')->assertOk()->assertSee('https://example.com/banner.jpg', false)->assertSee('Discover MacBook Air')->assertSee('A fresh everyday essential.')->assertSee('Explore collection')->assertDontSee('class="nova-hero"', false);
        $this->patchJson('/api/v1/admin/manage/banners/'.$id, $this->banner(['image_url' => 'https://example.com/new.jpg']))->assertOk();
        $this->get('/')->assertSee('https://example.com/new.jpg', false)->assertDontSee('https://example.com/banner.jpg', false);
        $this->deleteJson('/api/v1/admin/manage/banners/'.$id)->assertOk();
        $this->get('/')->assertDontSee('https://example.com/new.jpg', false);
        $this->patchJson('/api/v1/admin/manage/banners/'.$id.'/restore')->assertOk();
        $this->get('/')->assertSee('https://example.com/new.jpg', false);
    }

    public function test_only_scheduled_active_banners_are_visible_in_stable_order(): void
    {
        foreach (['inactive' => ['status' => 'inactive'], 'future' => ['start_date' => now()->addDay()], 'expired' => ['end_date' => now()->subDay()], 'deleted' => ['deleted_at' => now()]] as $name => $changes) {
            DB::table('storefront_banners')->insert($this->banner(['image_url' => 'https://example.com/'.$name.'.jpg'] + $changes));
        }
        DB::table('storefront_banners')->insert($this->banner(['image_url' => 'https://example.com/second.jpg', 'sort_order' => 2]));
        DB::table('storefront_banners')->insert($this->banner(['image_url' => 'https://example.com/first.jpg', 'sort_order' => 1]));
        $response = $this->get('/')->assertOk()->assertSeeInOrder(['https://example.com/first.jpg', 'https://example.com/second.jpg'], false);
        foreach (['inactive', 'future', 'expired', 'deleted'] as $name) {
            $response->assertDontSee('https://example.com/'.$name.'.jpg', false);
        }
    }

    public function test_banner_mutations_require_permissions_and_safe_urls(): void
    {
        $this->postJson('/api/v1/admin/manage/banners', $this->banner())->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active', 'admin_permissions' => ['catalog']]), 'sanctum');
        $this->postJson('/api/v1/admin/manage/banners', $this->banner())->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']), 'sanctum');
        $this->postJson('/api/v1/admin/manage/banners', $this->banner(['link_url' => 'javascript:alert(1)']))->assertUnprocessable();
        $this->postJson('/api/v1/admin/manage/banners', $this->banner(['image_url' => 'data:text/html,bad']))->assertUnprocessable();
        $this->postJson('/api/v1/admin/manage/banners', $this->banner(['start_date' => '2026-10-30', 'end_date' => '2026-10-29']))->assertUnprocessable();
    }
}
