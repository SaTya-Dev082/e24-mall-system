<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX customer_verification_pending_index ON customer_verification_deliveries (available_at, id) WHERE queued_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS customer_verification_pending_index');
    }
};
