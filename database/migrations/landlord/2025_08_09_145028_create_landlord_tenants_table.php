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
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->bigInteger('user_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('slug')->unique();
            $table->string('setup_step')->nullable();
            $table->text('setup_error')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
            $table->string('subdomain', 100)->unique()->nullable();
            $table->string('domain')->unique()->nullable();
            $table->string('database_name', 100)->unique();
            $table->string('database_host')->default('localhost');
            $table->string('status')->default('pending');
            $table->timestamp('last_login_at')->nullable();
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
