<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('parent_id');
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku', 100)->nullable()->unique();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->string('stock_status')->default('in_stock');
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('draft');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->integer('review_count')->default(0);
            $table->integer('sold_count')->default(0);
            $table->integer('view_count')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('category_id');
            $table->index('brand_id');
            $table->index('status');
            $table->index('price');
            $table->index('created_at');
            $table->index(['category_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index(['status', 'is_featured']);
            $table->index(['status', 'sold_count']);
            $table->index(['status', 'rating_avg']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('image_url');
            $table->string('alt_text')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->index('product_id');
            $table->index(['product_id', 'is_primary']);
            $table->index(['product_id', 'sort_order']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_status_check CHECK (status IN ('active', 'inactive'))");
            DB::statement("ALTER TABLE brands ADD CONSTRAINT brands_status_check CHECK (status IN ('active', 'inactive'))");
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_stock_status_check CHECK (stock_status IN ('in_stock', 'out_of_stock', 'preorder'))");
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('draft', 'active', 'inactive'))");
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_review_count_non_negative CHECK (review_count >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_sold_count_non_negative CHECK (sold_count >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_view_count_non_negative CHECK (view_count >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
