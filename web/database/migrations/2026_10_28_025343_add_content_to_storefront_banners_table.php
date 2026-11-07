<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_banners', function (Blueprint $table): void {
            $table->string('headline')->nullable();
            $table->text('description')->nullable();
            $table->string('button_text', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('storefront_banners', function (Blueprint $table): void {
            $table->dropColumn(['headline', 'description', 'button_text']);
        });
    }
};
