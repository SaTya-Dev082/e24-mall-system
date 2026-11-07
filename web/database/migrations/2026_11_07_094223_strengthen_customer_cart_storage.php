<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('carts')->select('user_id')->whereNotNull('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists()
            || DB::table('cart_items')->select('cart_id', 'product_id')->whereNull('product_variant_id')->groupBy('cart_id', 'product_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate carts or variant-less lines require reconciliation before adding uniqueness constraints. No records were removed.');
        }
        Schema::table('carts', function (Blueprint $table): void {
            $table->unique('user_id');
        });
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->boolean('is_selected')->default(true);
        });
        Schema::create('cart_merge_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('merge_key', 64);
            $table->timestampTz('created_at');
            $table->unique(['user_id', 'merge_key']);
        });
        DB::statement('CREATE UNIQUE INDEX cart_items_without_variant_unique ON cart_items (cart_id, product_id) WHERE product_variant_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_merge_receipts');
        DB::statement('DROP INDEX IF EXISTS cart_items_without_variant_unique');
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropColumn('is_selected'));
        Schema::table('carts', fn (Blueprint $table) => $table->dropUnique(['user_id']));
    }
};
