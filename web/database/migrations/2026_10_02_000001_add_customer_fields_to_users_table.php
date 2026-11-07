<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('password');
            $table->string('gender', 20)->nullable()->after('avatar');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('status')->default('active')->index()->after('date_of_birth');
            $table->timestampTz('phone_verified_at')->nullable()->after('email_verified_at');
            $table->softDeletesTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive', 'blocked'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
            $table->dropColumn([
                'phone',
                'avatar',
                'gender',
                'date_of_birth',
                'status',
                'phone_verified_at',
            ]);
        });
    }
};
