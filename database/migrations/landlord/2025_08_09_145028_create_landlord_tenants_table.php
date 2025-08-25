<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('setup_step')->nullable();
            $table->text('setup_error')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
            $table->string('subdomain', 100)->unique()->nullable();
            $table->string('domain')->unique()->nullable();
            $table->string('database_name', 100)->unique();
            $table->string('database_host')->default('localhost');

            // subscription  and billing
            $table->enum('plan_type',['free', 'starter', 'professional', 'enterprise'])->default('free');
            $table->enum('subscription_status', ['active', 'suspended', 'cancelled', 'trial'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('billing_email');

            // settings
            $table->string('status')->default('pending');
            $table->integer('max_users')->default(5);
            $table->integer('max_posts')->default(100);
            $table->integer('max_storage_mb')->default(1000);

            $table->softDeletesDatetime()->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('sessions');
    }
};
