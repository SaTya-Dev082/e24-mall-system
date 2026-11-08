<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->selectRaw('LOWER(TRIM(email))')->groupByRaw('LOWER(TRIM(email))')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate normalized emails require review before this migration. No accounts were changed or deleted.');
        }
        DB::statement('CREATE UNIQUE INDEX users_email_normalized_unique ON users (LOWER(TRIM(email)))');
        Schema::create('customer_verification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('available_at')->index();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_verification_deliveries');
        DB::statement('DROP INDEX IF EXISTS users_email_normalized_unique');
    }
};
