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
        Schema::table('users', fn (Blueprint $table) => $table->jsonb('admin_permissions')->nullable());
        foreach (['attributes', 'attribute_values', 'shipping_methods'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->softDeletesTz());
        }
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete());
        Schema::create('refund_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->string('status')->default('requested')->index();
            $table->text('review_note')->nullable();
            $table->timestampsTz();
            $table->index('order_id');
        });
        Schema::create('store_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('store_name')->default('E24');
            $table->string('support_email')->nullable();
            $table->string('support_phone', 50)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestampsTz();
        });
        DB::table('store_settings')->insert(['id' => 1, 'store_name' => 'E24', 'currency' => 'USD', 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
        Schema::dropIfExists('refund_requests');
        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('order_id');
        });
        foreach (['attributes', 'attribute_values', 'shipping_methods'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropSoftDeletesTz());
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('admin_permissions'));
    }
};
