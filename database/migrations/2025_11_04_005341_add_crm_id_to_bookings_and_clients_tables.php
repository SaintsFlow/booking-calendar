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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('crm_deal_id')->nullable()->after('id');
            $table->index('crm_deal_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('crm_contact_id')->nullable()->after('id');
            $table->index('crm_contact_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['crm_deal_id']);
            $table->dropColumn('crm_deal_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['crm_contact_id']);
            $table->dropColumn('crm_contact_id');
        });
    }
};
