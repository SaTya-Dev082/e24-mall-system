<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_banners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('image_url', 2048);
            $table->string('alt_text');
            $table->string('link_url', 2048)->nullable();
            $table->string('status')->default('inactive');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('start_date')->nullable();
            $table->timestampTz('end_date')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['status', 'sort_order', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_banners');
    }
};
