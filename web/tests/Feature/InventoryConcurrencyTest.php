<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Services\InventoryService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'pgsql' || ! str_starts_with((string) getenv('DB_DATABASE'), 'shop_inventory_test_')) {
            $this->markTestSkipped('Requires an isolated PostgreSQL inventory test database.');
        }
        parent::setUp();
    }

    public function test_two_concurrent_reservations_cannot_both_take_last_item(): void
    {
        $this->seed(CatalogSeeder::class);
        $variant = ProductVariant::query()->firstOrFail();
        app(InventoryService::class)->adjust($variant->id, 1 - $variant->stock_quantity, 'adjustment', 'One item left', 'last-item');
        $children = [];
        DB::beginTransaction();
        DB::table('products')->where('id', $variant->product_id)->lockForUpdate()->first();
        try {
            foreach (['buyer-a', 'buyer-b'] as $reference) {
                $code = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo "ready\\n"; flush(); try { app(\\App\\Services\\InventoryService::class)->reserve('.$variant->id.', 1, "'.$reference.'"); exit(0); } catch (\\Illuminate\\Validation\\ValidationException $e) { exit(2); }';
                $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($process);
                fclose($pipes[0]);
                stream_set_timeout($pipes[1], 10);
                $this->assertSame("ready\n", fgets($pipes[1]));
                $children[] = [$process, $pipes];
            }
        } finally {
            DB::commit();
        }
        $codes = [];
        foreach ($children as [$process, $pipes]) {
            stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $codes[] = proc_close($process);
            $this->assertSame('', $errors);
        }
        sort($codes);
        $this->assertSame([0, 2], $codes);
        $this->assertEquals(1, $variant->fresh()->reserved_quantity);
        $this->assertEquals(0, $variant->fresh()->available_stock);
        $this->assertEquals(1, DB::table('inventory_reservations')->count());
    }
}
