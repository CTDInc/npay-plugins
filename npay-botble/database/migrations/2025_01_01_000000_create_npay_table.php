<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npay_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->string('order_code', 128)->index();
            $table->string('transaction_id', 128)->nullable()->index();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 8)->default('VND');
            $table->string('bank_bin', 16)->nullable();
            $table->string('account_number', 64)->nullable();
            $table->text('memo')->nullable();
            $table->string('status', 32)->default('pending');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npay_transactions');
    }
};
