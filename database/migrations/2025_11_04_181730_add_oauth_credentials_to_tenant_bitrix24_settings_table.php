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
        Schema::table('tenant_bitrix24_settings', function (Blueprint $table) {
            $table->text('oauth_client_id')->nullable()->after('webhook_url');
            $table->text('oauth_client_secret')->nullable()->after('oauth_client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_bitrix24_settings', function (Blueprint $table) {
            $table->dropColumn(['oauth_client_id', 'oauth_client_secret']);
        });
    }
};
