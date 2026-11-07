<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 100)->unique();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->decimal('compare_price', 12, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('sold_quantity')->default(0);
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('product_id');
            $table->index('status');
            $table->index(['product_id', 'status']);
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->timestampsTz();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 100);
            $table->timestampsTz();

            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('product_variant_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['product_variant_id', 'attribute_id']);
            $table->index('attribute_value_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE product_variants ADD CONSTRAINT product_variants_status_check CHECK (status IN ('active', 'inactive'))");
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_stock_quantity_non_negative CHECK (stock_quantity >= 0)');
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_reserved_quantity_non_negative CHECK (reserved_quantity >= 0)');
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_sold_quantity_non_negative CHECK (sold_quantity >= 0)');
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_reserved_not_greater_than_stock CHECK (reserved_quantity <= stock_quantity)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('product_variants');
    }
};
