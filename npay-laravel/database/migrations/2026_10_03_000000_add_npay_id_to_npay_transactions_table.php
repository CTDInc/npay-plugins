<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('npay_transactions', function (Blueprint $table) {
            $table->string('npay_id', 64)->nullable()->unique()->after('id');
            $table->string('transfer_type', 8)->nullable()->after('npay_id');
        });
    }

    public function down(): void
    {
        Schema::table('npay_transactions', function (Blueprint $table) {
            $table->dropUnique(['npay_id']);
            $table->dropColumn(['npay_id', 'transfer_type']);
        });
    }
};
