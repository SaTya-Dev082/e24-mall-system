<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedInteger('low_stock_threshold')->default(5);
        });
        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestampTz('expires_at');
            $table->timestampsTz();
            $table->unique(['product_variant_id', 'reference']);
            $table->index(['status', 'expires_at']);
        });
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->integer('reserved_before');
            $table->integer('reserved_after');
            $table->string('reason', 500);
            $table->string('idempotency_key', 150);
            $table->string('reference', 100)->nullable();
            $table->timestampTz('created_at');
            $table->unique(['product_variant_id', 'idempotency_key']);
            $table->index(['product_variant_id', 'id']);
        });
        DB::table('product_variants')->orderBy('id')->chunkById(500, function ($variants): void {
            foreach ($variants as $variant) {
                DB::table('stock_movements')->insert([
                    'product_variant_id' => $variant->id, 'type' => 'opening',
                    'quantity' => $variant->stock_quantity, 'stock_before' => 0,
                    'stock_after' => $variant->stock_quantity, 'reserved_before' => 0,
                    'reserved_after' => $variant->reserved_quantity,
                    'reason' => 'Opening balance; earlier sales and reservations predate ledger.',
                    'idempotency_key' => 'opening', 'created_at' => now(),
                ]);
            }
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservation_valid CHECK (quantity > 0 AND returned_quantity BETWEEN 0 AND quantity AND status IN ('pending', 'committed', 'released', 'expired'))");
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT low_stock_threshold_valid CHECK (low_stock_threshold >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_reservations');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_variants DROP CONSTRAINT low_stock_threshold_valid');
        }
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('low_stock_threshold'));
    }
};
