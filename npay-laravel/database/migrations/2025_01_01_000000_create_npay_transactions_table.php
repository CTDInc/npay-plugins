<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('npay_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('gateway')->nullable()->index();
            $table->dateTime('transaction_date')->nullable()->index();
            $table->string('account_number')->nullable()->index();
            $table->string('sub_account')->nullable();
            $table->decimal('amount_in', 20, 2)->default(0);
            $table->decimal('amount_out', 20, 2)->default(0);
            $table->decimal('accumulated', 20, 2)->default(0);
            $table->string('code')->nullable()->index();
            $table->text('transaction_content')->nullable();
            $table->string('reference_number')->nullable()->index();
            $table->longText('body')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npay_transactions');
    }
};
