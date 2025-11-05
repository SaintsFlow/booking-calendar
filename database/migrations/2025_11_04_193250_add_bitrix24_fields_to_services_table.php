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
        Schema::table('services', function (Blueprint $table) {
            $table->string('bitrix24_product_id')->nullable()->after('tenant_id')->index();
            $table->enum('type', ['product', 'service'])->default('service')->after('bitrix24_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['bitrix24_product_id']);
            $table->dropColumn(['bitrix24_product_id', 'type']);
        });
    }
};
