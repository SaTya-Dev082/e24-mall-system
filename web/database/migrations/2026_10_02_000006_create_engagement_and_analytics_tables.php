<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['user_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status')->default('pending');
            $table->timestampsTz();

            $table->unique(['user_id', 'product_id', 'order_id']);
            $table->index('user_id');
            $table->index('product_id');
            $table->index('status');
            $table->index(['product_id', 'status']);
        });

        Schema::create('product_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at');

            $table->index('user_id');
            $table->index('session_id');
            $table->index('product_id');
            $table->index('created_at');
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->string('keyword');
            $table->integer('result_count')->default(0);
            $table->timestampTz('created_at');

            $table->index('user_id');
            $table->index('session_id');
            $table->index('keyword');
            $table->index('created_at');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->string('action', 100);
            $table->string('entity_type', 100)->nullable();
            $table->bigInteger('entity_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->index('user_id');
            $table->index('session_id');
            $table->index('action');
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });

        Schema::create('daily_sales_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date')->unique();
            $table->integer('orders_count')->default(0);
            $table->decimal('gross_sales', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('net_sales', 14, 2)->default(0);
            $table->integer('cancelled_orders_count')->default(0);
            $table->decimal('refunded_total', 14, 2)->default(0);
            $table->timestampsTz();
        });

        Schema::create('daily_product_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('views_count')->default(0);
            $table->integer('cart_adds_count')->default(0);
            $table->integer('orders_count')->default(0);
            $table->integer('units_sold')->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->timestampsTz();

            $table->unique(['summary_date', 'product_id']);
            $table->index('product_id');
            $table->index('summary_date');
        });

        Schema::create('daily_customer_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date')->unique();
            $table->integer('new_customers_count')->default(0);
            $table->integer('active_customers_count')->default(0);
            $table->integer('returning_customers_count')->default(0);
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating >= 1 AND rating <= 5)');
            DB::statement('ALTER TABLE search_logs ADD CONSTRAINT search_logs_result_count_non_negative CHECK (result_count >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_customer_summaries');
        Schema::dropIfExists('daily_product_summaries');
        Schema::dropIfExists('daily_sales_summaries');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('product_views');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('wishlists');
    }
};
