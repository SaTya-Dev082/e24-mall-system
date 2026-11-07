<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('province', 100);
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->decimal('fee', 12, 2);
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['shipping_method_id', 'province', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
