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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->enum('type', ['subscription', 'commission', 'payout'])->default('subscription');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('NGN');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['card', 'bank_transfer', 'qr_code', 'mobile_money'])->nullable();
            $table->string('gateway', 50);
            $table->string('gateway_transaction_id')->nullable();
            $table->json('metadata');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
