<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_notification_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_key');
            $table->string('audience', 16);
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('message');
            $table->string('action', 24);
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedBigInteger('upper_user_id')->default(0);
            $table->unsignedBigInteger('cursor_user_id')->default(0);
            $table->unsignedBigInteger('delivered_count')->default(0);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['created_by', 'request_key']);
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
            $table->foreignUuid('campaign_id')->nullable()->constrained('customer_notification_campaigns')->nullOnDelete();
            $table->unique(['campaign_id', 'notifiable_type', 'notifiable_id']);
            $table->index(['notifiable_type', 'notifiable_id', 'created_at', 'id'], 'notifications_customer_feed_index');
        });
        DB::statement('CREATE INDEX notifications_customer_unread_index ON notifications (notifiable_type, notifiable_id) WHERE read_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('customer_notification_campaigns');
    }
};
